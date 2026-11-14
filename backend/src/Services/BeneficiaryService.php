<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;
final class BeneficiaryService
{
    public function __construct(public readonly Store $store,public readonly array $user) {}
    public function owned(int $id): array
    {
        $p=$this->store->one('SELECT p.id,p.name,p.user_id,p.company_id,b.kind,b.status,b.contract_id,b.industry_unit_id,b.sponsor_patient_id FROM patients p JOIN beneficiary_links b ON b.patient_id=p.id AND b.company_id=p.company_id WHERE p.id=? AND p.company_id=? AND (p.user_id=? OR EXISTS (SELECT 1 FROM patient_representatives r WHERE r.patient_id=p.id AND r.user_id=? AND r.active=1))',[$id,$this->user['company_id'],$this->user['id'],$this->user['id']]);
        if(!$p) throw new HttpException(404,'NOT_FOUND','Beneficiário não encontrado.'); return $p;
    }
    public function eligible(int $id): array
    {
        $p=$this->owned($id);
        $current=$this->store->oneLocked('SELECT status,contract_id,industry_unit_id,sponsor_patient_id,kind FROM beneficiary_links WHERE patient_id=? AND company_id=?',[$id,$p['company_id']]);
        $p=array_replace($p,$current??[]);
        if($p['status']!=='active') throw new HttpException(403,'ELIGIBILITY_PENDING','Vínculo aguardando validação ou suspenso.');
        $contract=(new IndustryService($this->store,$this->user))->activeContract((int)$p['contract_id']);
        if($p['kind']==='dependent') {
            $sponsor=$this->store->oneLocked('SELECT id FROM beneficiary_links WHERE patient_id=? AND company_id=? AND contract_id=? AND kind=? AND status=?',[$p['sponsor_patient_id'],$p['company_id'],$p['contract_id'],'worker','active']);
            if(!(int)$contract['allow_dependents'] || !$sponsor) throw new HttpException(403,'DEPENDENT_NOT_ELIGIBLE','Cobertura do dependente indisponível.');
        }
        return $p+['contract'=>$contract];
    }
    public function list(): array
    {
        $rows=$this->store->all('SELECT p.id FROM patients p JOIN beneficiary_links b ON b.patient_id=p.id WHERE p.company_id=? AND (p.user_id=? OR EXISTS (SELECT 1 FROM patient_representatives r WHERE r.patient_id=p.id AND r.user_id=? AND r.active=1)) ORDER BY p.name',[$this->user['company_id'],$this->user['id'],$this->user['id']]);
        return array_map(fn($p)=>$this->owned((int)$p['id']),$rows);
    }
    public function appointments(): array
    {
        $rows=[];
        foreach($this->list() as $p) foreach($this->store->all('SELECT a.id,a.patient_id,a.professional_id,a.scheduled_at,a.status,a.modality,u.name AS professional_name,s.name AS service_unit,s.address FROM appointments a JOIN users u ON u.id=a.professional_id LEFT JOIN service_units s ON s.id=a.service_unit_id WHERE a.company_id=? AND a.patient_id=? ORDER BY a.scheduled_at DESC',[$this->user['company_id'],$p['id']]) as $a) $rows[]=$a+['patient_name'=>$p['name']];
        return $rows;
    }
    public function confirm(int $id): array
    {
        $a=$this->store->one('SELECT * FROM appointments WHERE id=? AND company_id=?',[$id,$this->user['company_id']]);
        if(!$a) throw new HttpException(404,'NOT_FOUND','Consulta não encontrada.'); $this->owned((int)$a['patient_id']);
        if(!in_array($a['status'],['expected','confirmed'],true)) throw new HttpException(409,'INVALID_STATE','Consulta não pode ser confirmada nesta situação.');
        return $this->store->transaction(function()use($a,$id){
            $q=$this->store->db->prepare('UPDATE appointments SET status=? WHERE id=? AND status IN (?,?)');$q->execute(['confirmed',$id,'expected','confirmed']);
            $this->store->execute('UPDATE continuity_cases SET status=?,version=version+1,updated_at=? WHERE appointment_id=? AND status IN (?,?)',['confirmed',date('Y-m-d H:i:s'),$id,'pending','confirmed']);
            Audit::record($this->store,$this->user,'self_confirmation','appointment',$id); return ['id'=>$id];
        });
    }
    public function support(int $id,array $d): array
    {
        $a=$this->store->one('SELECT * FROM appointments WHERE id=? AND company_id=?',[$id,$this->user['company_id']]);
        if(!$a) throw new HttpException(404,'NOT_FOUND','Consulta não encontrada.');$this->owned((int)$a['patient_id']);
        if(!in_array($a['status'],['expected','confirmed'],true)) throw new HttpException(409,'CASE_CLOSED','Selecione um retorno vigente para pedir apoio.');
        $barrier=Input::choice($d,'barrier',['transport','schedule','contact','personal','declined']);
        $note=Crypto::encrypt(Input::text($d,'administrative_note',1000,true));
        return $this->store->transaction(function()use($a,$id,$barrier,$note){
            $this->store->execute('UPDATE appointments SET status=status WHERE id=?',[$id]);
            $case=$this->store->one('SELECT * FROM continuity_cases WHERE appointment_id=?',[$id]);
            $caseId=$case?(int)$case['id']:$this->store->insert('continuity_cases',['company_id'=>$a['company_id'],'patient_id'=>$a['patient_id'],'appointment_id'=>$id,'status'=>$a['status']==='confirmed'?'confirmed':'pending','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            if($case && in_array($case['status'],['rescheduled','attended'],true)) throw new HttpException(409,'CASE_CLOSED','Selecione o retorno vigente para pedir apoio.');
            $this->store->execute('UPDATE continuity_cases SET barrier=?,barrier_resolved=0,version=version+1,updated_at=? WHERE id=?',[$barrier,date('Y-m-d H:i:s'),$caseId]);
            $this->store->insert('continuity_actions',['case_id'=>$caseId,'author_id'=>$this->user['id'],'action_type'=>'support_requested','administrative_note'=>$note,'payload'=>json_encode(['barrier'=>$barrier],JSON_THROW_ON_ERROR),'created_at'=>date('Y-m-d H:i:s')]);
            $this->store->insert('team_notifications',['company_id'=>$a['company_id'],'patient_id'=>$a['patient_id'],'case_id'=>$caseId,'event_type'=>'support_requested','created_at'=>date('Y-m-d H:i:s')]);
            Audit::record($this->store,$this->user,'support_requested','continuity_case',$caseId);return ['id'=>$caseId];
        });
    }
}
