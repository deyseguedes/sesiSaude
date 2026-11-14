<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;
final class CareService
{
    public readonly Access $access;
    public function __construct(public readonly Store $store, public readonly array $user) { $this->access=new Access($store,$user); }
    public function plan(int $id): array
    {
        $this->access->roles(['doctor','nurse']);
        $p=$this->store->one('SELECT * FROM care_plans WHERE id=? AND company_id=?',[$id,$this->user['company_id']]);
        if (!$p) throw new HttpException(404,'NOT_FOUND','Plano não encontrado.');
        $this->access->patient((int)$p['patient_id']); return $p;
    }
    public function event(int $plan,string $type,array $payload): void
    {
        $this->store->insert('care_plan_events',['plan_id'=>$plan,'author_id'=>$this->user['id'],'event_type'=>$type,'payload'=>Crypto::encrypt(json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),'created_at'=>date('Y-m-d H:i:s')]);
        Audit::record($this->store,$this->user,$type,'care_plan',$plan);
    }
    public function plans(): array
    {
        $this->access->roles(['doctor','nurse']);
        $ids=array_column($this->access->patients(),'id'); if (!$ids) return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));
        return $this->store->all("SELECT p.id,p.patient_id,t.name AS patient_name,p.professional_id,p.team_id,p.created_at,p.start_date,p.reassessment_date,p.status,p.version FROM care_plans p JOIN patients t ON t.id=p.patient_id WHERE p.company_id=? AND p.patient_id IN ($marks) ORDER BY p.reassessment_date,p.id",[$this->user['company_id'],...$ids]);
    }
    public function detail(int $id): array
    {
        $p=$this->plan($id); $p['clinical_notes']=Crypto::decrypt($p['clinical_notes']);
        $p['goals']=(new IndicatorService($this))->goals($id);
        foreach (['reviews'=>'care_plan_reviews','events'=>'care_plan_events','evolutions'=>'clinical_evolutions'] as $key=>$table) {
            $p[$key]=$this->store->all("SELECT * FROM $table WHERE plan_id=? ORDER BY created_at,id",[$id]);
            foreach ($p[$key] as &$row) { $field=$key==='events'?'payload':'notes'; $row[$field]=Crypto::decrypt($row[$field]); } unset($row);
        }
        $p['adherence']=$this->store->all('SELECT * FROM care_plan_adherence WHERE plan_id=? ORDER BY occurred_at,id',[$id]);
        Audit::record($this->store,$this->user,'read','care_plan',$id);
        return $p;
    }
    private function planData(array $d): array
    {
        $p=['patient_id'=>Input::id($d,'patient_id'),'professional_id'=>Input::id($d,'professional_id'),'team_id'=>Input::id($d,'team_id'),'start_date'=>Input::date($d,'start_date'),'reassessment_date'=>Input::date($d,'reassessment_date'),'objectives'=>Input::text($d,'objectives'),'interventions'=>Input::text($d,'interventions'),'status'=>Input::choice($d,'status',['active','paused','closed','reviewed']),'clinical_notes'=>Crypto::encrypt(Input::text($d,'clinical_notes',8000,true))];
        if ($p['reassessment_date']<$p['start_date']) Input::fail('reassessment_date');
        $this->access->team($p['patient_id'],$p['team_id'],$p['professional_id']); return $p;
    }
    public function create(array $d): array
    {
        $data=$this->planData($d);
        return $this->store->transaction(function() use ($data) {
            $id=$this->store->insert('care_plans',$data+['company_id'=>$this->user['company_id'],'created_at'=>date('Y-m-d H:i:s')]);
            $this->event($id,'plan_created',$data); return ['id'=>$id];
        });
    }
    public function update(int $id,array $d): array
    {
        $old=$this->plan($id); $data=$this->planData($d);
        if ($data['patient_id']!==(int)$old['patient_id']) Input::fail('patient_id imutável');
        $version=Input::id($d,'version');
        return $this->store->transaction(function() use($id,$data,$old,$version) {
            $q=$this->store->db->prepare('UPDATE care_plans SET professional_id=?,team_id=?,start_date=?,reassessment_date=?,objectives=?,interventions=?,status=?,clinical_notes=?,version=version+1 WHERE id=? AND version=?');
            $q->execute([$data['professional_id'],$data['team_id'],$data['start_date'],$data['reassessment_date'],$data['objectives'],$data['interventions'],$data['status'],$data['clinical_notes'],$id,$version]);
            if ($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Plano alterado por outro profissional. Recarregue.');
            $this->event($id,'plan_updated',['before'=>$old,'after'=>$data]); return ['id'=>$id,'version'=>$version+1];
        });
    }
    public function goals(int $id): array
    {
        $this->plan($id);
        return $this->store->all('SELECT * FROM care_plan_goals WHERE plan_id=? ORDER BY id',[$id]);
    }
    public function review(int $id,array $d): array
    {
        $p=$this->plan($id); $notes=Crypto::encrypt(Input::text($d,'notes',8000));
        $outcome=Input::choice($d,'outcome',['reviewed','closed','active']);
        $date=Input::date($d,'reassessment_date'); $version=Input::id($d,'version');
        if ($date<$p['start_date']) Input::fail('reassessment_date');
        return $this->store->transaction(function() use($id,$notes,$outcome,$date,$version) {
            $q=$this->store->db->prepare('UPDATE care_plans SET status=?,reassessment_date=?,version=version+1 WHERE id=? AND version=?'); $q->execute([$outcome,$date,$id,$version]);
            if ($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Recarregue o plano antes de revisar.');
            $review=$this->store->insert('care_plan_reviews',['plan_id'=>$id,'author_id'=>$this->user['id'],'notes'=>$notes,'outcome'=>$outcome,'created_at'=>date('Y-m-d H:i:s')]);
            $this->event($id,'plan_reviewed',['review_id'=>$review,'outcome'=>$outcome]); return ['id'=>$review];
        });
    }
    public function evolution(int $id,array $d): array
    {
        $this->plan($id); $kind=Input::choice($d,'kind',$this->user['role']==='nurse'?['nursing','vital_signs','education']:['clinical']);
        $notes=Crypto::encrypt(Input::text($d,'notes',8000));
        return $this->store->transaction(function()use($id,$kind,$notes){
            $record=$this->store->insert('clinical_evolutions',['plan_id'=>$id,'author_id'=>$this->user['id'],'kind'=>$kind,'notes'=>$notes,'created_at'=>date('Y-m-d H:i:s')]);
            $this->event($id,'evolution_recorded',['evolution_id'=>$record]); return ['id'=>$record];
        });
    }
    public function adherence(int $id,array $d): array
    {
        $this->plan($id); $description=Input::text($d,'action_description',500);
        if (!is_bool($d['completed']??null)) Input::fail('completed');
        $source=Input::choice($d,'source',['professional','self_reported']); $at=Input::timestamp($d,'occurred_at');
        return $this->store->transaction(function()use($id,$d,$description,$source,$at){
            $record=$this->store->insert('care_plan_adherence',['plan_id'=>$id,'author_id'=>$this->user['id'],'action_description'=>$description,'completed'=>(int)$d['completed'],'source'=>$source,'occurred_at'=>$at]);
            $this->event($id,'adherence_recorded',['adherence_id'=>$record]); return ['id'=>$record];
        });
    }
}
