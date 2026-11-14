<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;
final class EngagementService
{
    public const STAGES=['invited','interested','welcomed','participating','followup','completed','opted_out'];
    public function __construct(private Store $store,private array $user) {}
    private function editorial(IndustryService $industry): void
    {
        if(in_array($this->user['role'],['doctor','nurse'],true) && $industry->company===(int)$this->user['company_id']) return;
        $industry->manage();
    }
    public function contents(IndustryService $industry): array
    {
        $this->editorial($industry); return $this->store->all('SELECT * FROM education_contents WHERE company_id=? ORDER BY id DESC',[$industry->company]);
    }
    public function content(IndustryService $industry,array $d,?int $id=null): array
    {
        $this->editorial($industry); $category=Input::choice($d,'category',['platform','aps']);
        $duration=Input::id($d,'duration_seconds'); if($duration>600) Input::fail('vídeos rápidos de até 10 minutos');
        $data=['title'=>Input::text($d,'title',180),'summary'=>Input::text($d,'summary',2000),'transcript'=>Input::text($d,'transcript',10000),'category'=>$category,'video_url'=>SchedulingService::httpsUrl($d,'video_url',true),'duration_seconds'=>$duration,'updated_at'=>date('Y-m-d H:i:s')];
        return $this->store->transaction(function()use($industry,$d,$id,$data){
            if($id===null) $id=$this->store->insert('education_contents',$data+['company_id'=>$industry->company,'created_by'=>$this->user['id'],'created_at'=>date('Y-m-d H:i:s')]);
            else {
                $version=Input::id($d,'version'); $columns=array_keys($data);
                $q=$this->store->db->prepare('UPDATE education_contents SET '.implode(',',array_map(fn($c)=>$c.'=?',$columns)).',status=?,reviewed_by=NULL,review_note=NULL,version=version+1 WHERE id=? AND company_id=? AND version=?');
                $q->execute([...array_values($data),'draft',$id,$industry->company,$version]); if($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Conteúdo alterado. Recarregue.');
            }
            $industry->audit('content_drafted','content',$id); return ['id'=>$id];
        });
    }
    public function publish(IndustryService $industry,int $id,array $d): array
    {
        $this->editorial($industry); $content=$this->store->one('SELECT * FROM education_contents WHERE id=? AND company_id=?',[$id,$industry->company]);
        if(!$content) throw new HttpException(404,'NOT_FOUND','Conteúdo não encontrado.');
        if($content['category']==='aps' && !in_array($this->user['role'],['doctor','nurse'],true)) throw new HttpException(403,'CLINICAL_REVIEW_REQUIRED','Conteúdo APS precisa de revisão por médico ou enfermeiro.');
        $review=Input::text($d,'review_note',1000); $version=Input::id($d,'version');
        return $this->store->transaction(function()use($industry,$id,$review,$version){
            $q=$this->store->db->prepare('UPDATE education_contents SET status=?,reviewed_by=?,review_note=?,updated_at=?,version=version+1 WHERE id=? AND company_id=? AND version=?');$q->execute(['published',$this->user['id'],$review,date('Y-m-d H:i:s'),$id,$industry->company,$version]);
            if($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Conteúdo alterado. Recarregue.');$industry->audit('content_published','content',$id);return ['id'=>$id];
        });
    }
    public function library(): array
    {
        if(!in_array($this->user['role'],['worker','dependent'],true)) throw new HttpException(403,'FORBIDDEN','Biblioteca do beneficiário.');
        return $this->store->all('SELECT c.id,c.title,c.summary,c.transcript,c.category,c.video_url,c.duration_seconds,c.version,p.completed_at FROM education_contents c LEFT JOIN content_progress p ON p.content_id=c.id AND p.user_id=? AND p.content_version=c.version WHERE c.company_id=? AND c.status=? ORDER BY c.category,c.id',[$this->user['id'],$this->user['company_id'],'published']);
    }
    public function complete(int $id): array
    {
        $content=null;foreach($this->library() as $c) if((int)$c['id']===$id) $content=$c;
        if(!$content) throw new HttpException(404,'NOT_FOUND','Conteúdo não encontrado.');
        if(!$this->store->one('SELECT user_id FROM content_progress WHERE content_id=? AND user_id=? AND content_version=?',[$id,$this->user['id'],$content['version']])) $this->store->insert('content_progress',['content_id'=>$id,'user_id'=>$this->user['id'],'content_version'=>$content['version'],'completed_at'=>date('Y-m-d H:i:s')]);
        Audit::record($this->store,$this->user,'content_completion_self_reported','content',$id);return ['id'=>$id,'completed'=>true];
    }
    public function campaigns(IndustryService $industry): array
    {
        $industry->manage(); $rows=$this->store->all('SELECT * FROM campaigns WHERE company_id=? ORDER BY id DESC',[$industry->company]);
        foreach($rows as &$row) {
            $row['content_ids']=array_map('intval',array_column($this->store->all('SELECT content_id FROM campaign_contents WHERE campaign_id=?',[$row['id']]),'content_id'));
            $n=(int)$this->store->one('SELECT COUNT(*) n FROM campaign_participants WHERE campaign_id=?',[$row['id']])['n'];
            $row['participants']=$n>=5?$n:null; $row['suppressed']=$n<5; $row['stages']=[];
            if($n>=5) {
                $stages=$this->store->all('SELECT stage,COUNT(*) n FROM campaign_participants WHERE campaign_id=? GROUP BY stage',[$row['id']]);
                // Complementary suppression avoids deriving a small bucket from totals.
                if(array_filter($stages,fn($stage)=>(int)$stage['n']<5)) {$row['participants']=null;$row['suppressed']=true;}
                else foreach($stages as $stage) $row['stages'][]=['stage'=>$stage['stage'],'count'=>(int)$stage['n']];
            }
        }
        return $rows;
    }
    public function createCampaign(IndustryService $industry,array $d): array
    {
        $industry->manage(); $start=Input::date($d,'start_date');$end=Input::date($d,'end_date'); if($end<$start) Input::fail('período do ciclo');
        $unit=$d['industry_unit_id']??null; if($unit!==null && (!is_int($unit) || !$this->store->one('SELECT id FROM industry_units WHERE id=? AND company_id=? AND active=1',[$unit,$industry->company]))) Input::fail('unidade industrial');
        $contentIds=$d['content_ids']??[];if(!is_array($contentIds) || !array_is_list($contentIds) || count($contentIds)>20 || !$contentIds) Input::fail('conteúdos do ciclo');
        foreach($contentIds as $content) if(!is_int($content) || !$this->store->one('SELECT id FROM education_contents WHERE id=? AND company_id=? AND status=?',[$content,$industry->company,'published'])) Input::fail('conteúdo publicado');
        $data=['company_id'=>$industry->company,'title'=>Input::text($d,'title',180),'objective'=>Input::text($d,'objective',2000),'cycle_label'=>Input::text($d,'cycle_label',100),'start_date'=>$start,'end_date'=>$end,'industry_unit_id'=>$unit,'audience'=>Input::choice($d,'audience',['worker','dependent','all']),'created_by'=>$this->user['id'],'created_at'=>date('Y-m-d H:i:s')];
        return $this->store->transaction(function()use($industry,$data,$contentIds){$id=$this->store->insert('campaigns',$data);foreach(array_unique($contentIds) as $content) $this->store->insert('campaign_contents',['campaign_id'=>$id,'content_id'=>$content]);$industry->audit('campaign_created','campaign',$id);return ['id'=>$id];});
    }
    public function campaignStatus(IndustryService $industry,int $id,array $d): array
    {
        $industry->manage(); $c=$this->store->one('SELECT * FROM campaigns WHERE id=? AND company_id=?',[$id,$industry->company]);if(!$c) throw new HttpException(404,'NOT_FOUND','Campanha não encontrada.');
        $status=Input::choice($d,'status',['active','closed']); $version=Input::id($d,'version');
        if($c['status']==='closed' && $status==='active') throw new HttpException(409,'CYCLE_CLOSED','Crie um novo ciclo para uma nova campanha.');
        if($status==='active' && $c['end_date']<date('Y-m-d')) Input::fail('campanha vencida');
        return $this->store->transaction(function()use($industry,$c,$id,$status,$version){
            if($status==='active' && $this->store->one('SELECT cc.content_id FROM campaign_contents cc JOIN education_contents e ON e.id=cc.content_id WHERE cc.campaign_id=? AND e.status<>?',[$id,'published'])) Input::fail('conteúdo ainda não publicado');
            $q=$this->store->db->prepare('UPDATE campaigns SET status=?,version=version+1 WHERE id=? AND company_id=? AND version=?');$q->execute([$status,$id,$industry->company,$version]);if($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Campanha alterada. Recarregue.');
            $created=0;
            if($status==='active') {
                $links=$this->store->all('SELECT b.* FROM beneficiary_links b JOIN service_contracts c ON c.id=b.contract_id AND c.company_id=b.company_id JOIN industry_units u ON u.id=b.industry_unit_id AND u.company_id=b.company_id WHERE b.company_id=? AND b.status=? AND c.status=? AND c.start_date<=? AND c.end_date>=? AND u.active=1',[$industry->company,'active','active',date('Y-m-d'),date('Y-m-d')]);
                foreach($links as $b) {
                    if($c['industry_unit_id']!==null && (int)$b['industry_unit_id']!==(int)$c['industry_unit_id']) continue;
                    if($c['audience']!=='all' && $b['kind']!==$c['audience']) continue;
                    if($b['kind']==='dependent' && (!$this->store->one('SELECT id FROM service_contracts WHERE id=? AND allow_dependents=1',[$b['contract_id']]) || !$this->store->one('SELECT id FROM beneficiary_links WHERE patient_id=? AND company_id=? AND contract_id=? AND kind=? AND status=?',[$b['sponsor_patient_id'],$industry->company,$b['contract_id'],'worker','active']))) continue;
                    if($this->store->one('SELECT id FROM campaign_participants WHERE campaign_id=? AND patient_id=?',[$id,$b['patient_id']])) continue;
                    $participant=$this->store->insert('campaign_participants',['campaign_id'=>$id,'patient_id'=>$b['patient_id'],'updated_at'=>date('Y-m-d H:i:s')]);
                    $this->event($participant,'invited'); $created++;
                }
            }
            $industry->audit('campaign_'.$status,'campaign',$id);return ['id'=>$id,'invited'=>$created];
        });
    }
    private function event(int $participant,string $type): void { $this->store->insert('campaign_events',['participant_id'=>$participant,'author_id'=>$this->user['id'],'event_type'=>$type,'created_at'=>date('Y-m-d H:i:s')]); }
    public function mine(): array
    {
        if(!in_array($this->user['role'],['worker','dependent'],true)) throw new HttpException(403,'FORBIDDEN','Área do beneficiário.');
        $rows=$this->store->all('SELECT cp.id AS participant_id,cp.patient_id,cp.stage,cp.version,c.id,c.title,c.objective,c.cycle_label,c.start_date,c.end_date FROM campaign_participants cp JOIN campaigns c ON c.id=cp.campaign_id JOIN patients p ON p.id=cp.patient_id AND p.company_id=c.company_id WHERE p.user_id=? AND c.company_id=? AND c.status=? AND c.start_date<=? AND c.end_date>=? ORDER BY c.end_date',[$this->user['id'],$this->user['company_id'],'active',date('Y-m-d'),date('Y-m-d')]);
        $result=[];
        foreach($rows as $c) {
            try{(new BeneficiaryService($this->store,$this->user))->eligible((int)$c['patient_id']);}catch(HttpException){continue;}
            $c['content_ids']=array_map('intval',array_column($this->store->all('SELECT content_id FROM campaign_contents WHERE campaign_id=?',[$c['id']]),'content_id'));$result[]=$c;
        }
        return $result;
    }
    public function selfStage(int $id,array $d): array
    {
        $stage=Input::choice($d,'stage',['interested','participating','completed','opted_out']);$version=Input::id($d,'version');
        $c=null;foreach($this->mine() as $row) if((int)$row['participant_id']===$id) $c=$row;
        if(!$c) throw new HttpException(404,'NOT_FOUND','Participação não encontrada.');
        if($stage==='completed') {
            $required=$this->store->all('SELECT e.id,e.status,p.completed_at FROM campaign_contents cc JOIN education_contents e ON e.id=cc.content_id LEFT JOIN content_progress p ON p.content_id=e.id AND p.user_id=? AND p.content_version=e.version WHERE cc.campaign_id=?',[$this->user['id'],$c['id']]);
            if(!$required) Input::fail('conteúdo do ciclo');foreach($required as $r) if($r['status']!=='published' || !$r['completed_at']) Input::fail('concluir os conteúdos antes do ciclo');
        }
        return $this->changeStage($id,$stage,$version);
    }
    private function changeStage(int $id,string $stage,int $version): array
    {
        return $this->store->transaction(function()use($id,$stage,$version){$q=$this->store->db->prepare('UPDATE campaign_participants SET stage=?,updated_at=?,version=version+1 WHERE id=? AND version=?');$q->execute([$stage,date('Y-m-d H:i:s'),$id,$version]);if($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Participação alterada. Recarregue.');$this->event($id,$stage);Audit::record($this->store,$this->user,'campaign_stage','campaign_participant',$id);return ['id'=>$id];});
    }
    private function staffParticipant(int $id): array
    {
        $access=new Access($this->store,$this->user);$access->roles(['doctor','nurse','navigator']);
        $p=$this->store->one('SELECT cp.*,c.title,c.company_id FROM campaign_participants cp JOIN campaigns c ON c.id=cp.campaign_id WHERE cp.id=? AND c.company_id=?',[$id,$this->user['company_id']]);if(!$p) throw new HttpException(404,'NOT_FOUND','Participação não encontrada.');$access->patient((int)$p['patient_id'],false); return $p;
    }
    public function pipeline(): array
    {
        $patients=(new Access($this->store,$this->user))->patients();$rows=[];
        foreach($patients as $p) foreach($this->store->all('SELECT cp.*,c.title,c.cycle_label,c.end_date FROM campaign_participants cp JOIN campaigns c ON c.id=cp.campaign_id WHERE cp.patient_id=? AND c.company_id=? ORDER BY c.end_date',[$p['id'],$this->user['company_id']]) as $r) {
            $r['patient_name']=$p['name'];$r['tasks']=$this->store->all('SELECT * FROM campaign_tasks WHERE participant_id=? ORDER BY due_date,id',[$r['id']]);
            foreach($r['tasks'] as &$task) $task['administrative_note']=Crypto::decrypt($task['administrative_note']);unset($task);
            $r['history']=$this->store->all('SELECT * FROM campaign_events WHERE participant_id=? ORDER BY id',[$r['id']]); $rows[]=$r;
        }
        return $rows;
    }
    public function staffStage(int $id,array $d): array
    {
        $c=$this->staffParticipant($id);$stage=Input::choice($d,'stage',['welcomed','participating','followup','opted_out']);
        if($c['stage']==='opted_out' && $stage!=='opted_out') throw new HttpException(409,'OPTED_OUT','A pessoa recusou participação; aguarde manifestação dela.');
        return $this->changeStage($id,$stage,Input::id($d,'version'));
    }
    public function task(int $id,array $d): array
    {
        $p=$this->staffParticipant($id);if($p['stage']==='opted_out') throw new HttpException(409,'OPTED_OUT','Não criar contato de campanha para quem recusou.');
        $assigned=Input::id($d,'assigned_to');$u=$this->store->one('SELECT id,role,company_id FROM users WHERE id=? AND company_id=? AND active=1',[$assigned,$this->user['company_id']]);if(!$u) Input::fail('responsável');(new Access($this->store,$u))->patient((int)$p['patient_id'],false);
        $data=['participant_id'=>$id,'assigned_to'=>$assigned,'created_by'=>$this->user['id'],'kind'=>Input::choice($d,'kind',['contact','welcome','followup']),'due_date'=>Input::date($d,'due_date'),'administrative_note'=>Crypto::encrypt(Input::text($d,'administrative_note',1000,true)),'created_at'=>date('Y-m-d H:i:s')];
        return $this->store->transaction(function()use($data,$id){$task=$this->store->insert('campaign_tasks',$data);$this->event($id,'task_created');Audit::record($this->store,$this->user,'campaign_task_created','campaign_task',$task);return ['id'=>$task];});
    }
    public function updateTask(int $id,array $d): array
    {
        $task=$this->store->one('SELECT t.* FROM campaign_tasks t JOIN campaign_participants cp ON cp.id=t.participant_id JOIN campaigns c ON c.id=cp.campaign_id WHERE t.id=? AND c.company_id=?',[$id,$this->user['company_id']]);if(!$task) throw new HttpException(404,'NOT_FOUND','Tarefa não encontrada.');$this->staffParticipant((int)$task['participant_id']);
        if((int)$task['assigned_to']!==(int)$this->user['id']) throw new HttpException(403,'FORBIDDEN','Somente o responsável pode concluir a tarefa.');
        $status=Input::choice($d,'status',['done','deferred']);$version=Input::id($d,'version');$note=Crypto::encrypt(Input::text($d,'administrative_note',1000,true));
        return $this->store->transaction(function()use($id,$task,$status,$version,$note){$q=$this->store->db->prepare('UPDATE campaign_tasks SET status=?,administrative_note=?,version=version+1 WHERE id=? AND version=?');$q->execute([$status,$note,$id,$version]);if($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Tarefa alterada.');$this->event((int)$task['participant_id'],'task_'.$status);return ['id'=>$id];});
    }
    public function assignees(int $id): array
    {
        $p=$this->staffParticipant($id);
        return $this->store->all('SELECT DISTINCT u.id,u.name,u.role FROM users u JOIN team_members m ON m.user_id=u.id JOIN care_team_assignments a ON a.team_id=m.team_id JOIN teams t ON t.id=a.team_id AND t.company_id=u.company_id WHERE u.company_id=? AND u.active=1 AND u.role IN (?,?,?) AND a.patient_id=? AND a.company_id=u.company_id AND a.active=1 ORDER BY u.name',[$this->user['company_id'],'doctor','nurse','navigator',$p['patient_id']]);
    }
}
