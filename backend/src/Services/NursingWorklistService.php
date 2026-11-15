<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{HttpException,Input};

final class NursingWorklistService
{
    public function __construct(private readonly CareService $care) {}
    private function authorize(): void { $this->care->access->roles(['doctor','nurse']); }
    private function shortcuts(array $row): array
    {
        $own=(int)$this->care->user['id'];
        $teamAllowed=!$row['team_id'] || $this->care->store->one('SELECT user_id FROM team_members WHERE team_id=? AND user_id=?',[$row['team_id'],$own]);
        $row['can_take']=$row['status']!=='resolved' && $teamAllowed && (!$row['assigned_to'] || (int)$row['assigned_to']===$own);
        $row['can_confirm']=false;
        if($row['source_type']==='case') {
            $appointment=$this->care->store->one('SELECT a.status,a.scheduled_at FROM continuity_cases c JOIN appointments a ON a.id=c.appointment_id AND a.company_id=c.company_id AND a.patient_id=c.patient_id WHERE c.id=? AND c.company_id=? AND c.patient_id=?',[$row['source_id'],$row['company_id'],$row['patient_id']]);
            $row['can_confirm']=$row['status']!=='resolved' && $appointment && $appointment['status']==='expected' && $appointment['scheduled_at']>=date('Y-m-d H:i:s');
        }
        return $row;
    }
    private function event(int $id,string $type,string $note='',array $payload=[]): void
    {
        $this->care->store->insert('care_pending_events',['item_id'=>$id,'author_id'=>$this->care->user['id'],'event_type'=>$type,'note'=>Crypto::encrypt($note),'payload'=>Crypto::encrypt(json_encode($payload,JSON_THROW_ON_ERROR)),'created_at'=>date('Y-m-d H:i:s')]);
        Audit::record($this->care->store,$this->care->user,'pending_'.$type,'care_pending_item',$id);
    }
    private function item(int $id,bool $lock=false): array
    {
        $this->authorize();
        $sql='SELECT * FROM care_pending_items WHERE id=? AND company_id=?';
        $row=$lock?$this->care->store->oneLocked($sql,[$id,$this->care->user['company_id']]):$this->care->store->one($sql,[$id,$this->care->user['company_id']]);
        if(!$row) throw new HttpException(404,'NOT_FOUND','Pendência não encontrada.');
        $this->care->access->patient((int)$row['patient_id']); return $row;
    }
    public function sync(): array
    {
        $this->authorize();
        (new ContinuityService($this->care))->sync();
        $s=$this->care->store; $candidates=[]; $ids=[];
        $horizon=date('Y-m-d',time()+7*86400).' 23:59:59'; $now=date('Y-m-d H:i:s');
        foreach($this->care->access->patients() as $patient) {
            $patientId=(int)$patient['id'];$ids[]=$patientId;
            $scope=[$this->care->user['company_id'],$patientId];
            // Confirmation is not attendance: include past confirmed appointments even without a legacy case.
            foreach($s->all('SELECT a.id FROM appointments a WHERE a.company_id=? AND a.patient_id=? AND a.status=? AND a.scheduled_at<? AND NOT EXISTS (SELECT 1 FROM continuity_cases c WHERE c.appointment_id=a.id)',[...$scope,'confirmed',$now]) as $appointment) {
                try {
                    $s->transaction(function()use($s,$appointment,$patientId){
                        $caseId=$s->insert('continuity_cases',['company_id'=>$this->care->user['company_id'],'patient_id'=>$patientId,'appointment_id'=>$appointment['id'],'status'=>'confirmed','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
                        Audit::record($s,$this->care->user,'case_created','continuity_case',$caseId);
                    });
                } catch(\PDOException $error) { if(!$s->one('SELECT id FROM continuity_cases WHERE appointment_id=?',[$appointment['id']])) throw $error; }
            }
            $teams=$s->all('SELECT t.id FROM teams t JOIN care_team_assignments a ON a.team_id=t.id WHERE a.company_id=? AND a.patient_id=? AND a.active=1 AND t.company_id=a.company_id',$scope);
            $team=count($teams)===1?(int)$teams[0]['id']:null;
            $append=function(string $type,array $row,string $kind,string $title,string $reason,string $next,string $due,?int $professional,?int $teamId,?int $unit=null,bool $help=false,string $revision='')use(&$candidates,$patientId){
                $candidates[]=['company_id'=>$this->care->user['company_id'],'patient_id'=>$patientId,'source_type'=>$type,'source_id'=>(int)$row['id'],'source_revision'=>$revision,'kind'=>$kind,'title'=>$title,'reason'=>$reason,'next_action'=>$next,'team_id'=>$teamId,'service_unit_id'=>$unit,'professional_id'=>$professional,'due_at'=>$due,'help_requested'=>(int)$help];
            };
            foreach($s->all('SELECT c.*,a.scheduled_at,a.status AS appointment_status,a.professional_id,a.service_unit_id FROM continuity_cases c JOIN appointments a ON a.id=c.appointment_id AND a.company_id=c.company_id AND a.patient_id=c.patient_id WHERE c.company_id=? AND c.patient_id=?',$scope) as $case) {
                if(!in_array($case['appointment_status'],['expected','confirmed'],true)) continue;
                $support=$s->one('SELECT MAX(id) n FROM continuity_actions WHERE case_id=? AND action_type=?',[$case['id'],'support_requested']);
                $trigger=$s->one('SELECT MAX(id) n FROM continuity_actions WHERE case_id=? AND action_type IN (?,?)',[$case['id'],'support_requested','barrier']);
                $help=(int)($support['n']??0)>0;
                $barrier=$case['barrier'] && $case['barrier']!=='declined' && !(int)$case['barrier_resolved'];
                $late=$case['scheduled_at']<$now;
                $unconfirmed=$case['appointment_status']==='expected' && $case['scheduled_at']<=$horizon;
                if(!$help && !$barrier && !$late && !$unconfirmed) continue;
                $reason=$help?'Há um pedido voluntário de apoio aguardando acompanhamento.':($barrier?'Há uma dificuldade informada sem resolução registrada.':($late?'A data do retorno passou e não há comparecimento registrado.':'O retorno ocorrerá nos próximos sete dias e ainda não está confirmado.'));
                $append('case',$case,'return_followup',$help?'Pedido de apoio':($barrier?'Dificuldade relatada':($late?'Verificar comparecimento':'Confirmar próximo retorno')),$reason,$help||$barrier?'Registrar contato humano e pactuar o próximo passo.':($late?'Verificar o comparecimento com a pessoa ou equipe.':'Confirmar a programação com a pessoa.'),$case['scheduled_at'],(int)$case['professional_id'],$team,$case['service_unit_id']===null?null:(int)$case['service_unit_id'],$help,(string)($trigger['n']??0));
            }
            $clinicalReturn=(new ClinicalReturnService($this->care))->view($patientId,false);
            if($clinicalReturn['available'] && !$clinicalReturn['attendance'] && $clinicalReturn['due_date']<=substr($horizon,0,10)) {
                $append('clinical_return',$clinicalReturn,'medical_return','Retorno definido pelo médico','Existe retorno médico confirmado com prazo até os próximos sete dias.','Contatar a pessoa, conferir agenda e registrar o próximo passo.',$clinicalReturn['due_date'].' 23:59:59',$clinicalReturn['doctor_id'],$clinicalReturn['team_id'],null,false,(string)$clinicalReturn['version']);
            }
            foreach($s->all('SELECT * FROM care_plans WHERE company_id=? AND patient_id=? AND status IN (?,?) AND reassessment_date<=?',[...$scope,'active','reviewed',substr($horizon,0,10)]) as $plan) {
                $append('plan',$plan,'plan_review','Reavaliar plano','O plano possui reavaliação prevista até os próximos sete dias.','Conferir a programação e encaminhar à equipe responsável.',$plan['reassessment_date'].' 23:59:59',(int)$plan['professional_id'],(int)$plan['team_id'],null,false,$plan['reassessment_date']);
            }
            foreach($s->all('SELECT * FROM referrals WHERE company_id=? AND patient_id=? AND status=?',[...$scope,'requested']) as $referral) {
                $append('referral',$referral,'referral_followup','Encaminhamento pendente','Existe um encaminhamento com status solicitado.','Verificar recebimento com o profissional destinatário.',$referral['created_at'],(int)$referral['to_professional_id'],$team);
            }
            foreach($s->all('SELECT t.* FROM campaign_tasks t JOIN campaign_participants cp ON cp.id=t.participant_id JOIN campaigns c ON c.id=cp.campaign_id WHERE c.company_id=? AND cp.patient_id=? AND t.status IN (?,?)',[...$scope,'open','deferred']) as $task) {
                $append('campaign_task',$task,'campaign_followup','Tarefa de acolhimento','Há um próximo passo de campanha ainda não concluído.','Conferir o próximo passo com o responsável.',$task['due_date'].' 23:59:59',(int)$task['assigned_to'],$team);
            }
        }
        return $s->transaction(function()use($s,$candidates,$ids){
            $seen=[]; $created=0;$closed=0;
            foreach($candidates as $data) {
                $old=$s->oneLocked('SELECT * FROM care_pending_items WHERE company_id=? AND source_type=? AND source_id=? AND kind=?',[$data['company_id'],$data['source_type'],$data['source_id'],$data['kind']]);
                if(!$old) {
                    try { $id=$s->insert('care_pending_items',$data+['created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]); $created++; $this->event($id,'identified','Pendência identificada a partir de registro existente.'); }
                    catch(\PDOException $error) { $old=$s->oneLocked('SELECT * FROM care_pending_items WHERE company_id=? AND source_type=? AND source_id=? AND kind=?',[$data['company_id'],$data['source_type'],$data['source_id'],$data['kind']]); if(!$old) throw $error; $id=(int)$old['id']; }
                } else $id=(int)$old['id'];
                if($old) {
                    $changes=[];
                    foreach($data as $key=>$value) if($old[$key]!=$value) $changes[$key]=$value;
                    if($old['status']==='resolved' && ($old['source_revision']!==$data['source_revision'] || $data['source_type']==='clinical_return')) $changes['status']='open';
                    if($changes) {
                        $sql=implode(',',array_map(fn($key)=>$key.'=?',array_keys($changes)));
                        $s->execute('UPDATE care_pending_items SET '.$sql.',version=version+1,updated_at=? WHERE id=?',[...array_values($changes),date('Y-m-d H:i:s'),$id]);
                        $this->event($id,'source_updated','Registro de origem atualizado.',['changed_fields'=>array_keys($changes)]);
                    }
                }
                $seen[]=$id;
            }
            foreach($ids as $patientId) foreach($s->all('SELECT id FROM care_pending_items WHERE company_id=? AND patient_id=? AND source_type<>? AND status<>?',[$this->care->user['company_id'],$patientId,'manual','resolved']) as $row) {
                if(in_array((int)$row['id'],$seen,true)) continue;
                $s->execute('UPDATE care_pending_items SET status=?,version=version+1,updated_at=? WHERE id=?',['resolved',date('Y-m-d H:i:s'),$row['id']]);
                $this->event((int)$row['id'],'source_resolved','A origem não apresenta mais esta pendência.');$closed++;
            }
            return ['created'=>$created,'resolved'=>$closed];
        });
    }
    private function professionals(int $patientId): array
    {
        return $this->care->store->all('SELECT DISTINCT u.id,u.name,u.role,m.team_id FROM users u JOIN team_members m ON m.user_id=u.id JOIN teams t ON t.id=m.team_id AND t.company_id=u.company_id JOIN care_team_assignments a ON a.team_id=t.id AND a.company_id=t.company_id JOIN team_members own ON own.team_id=t.id WHERE a.patient_id=? AND a.active=1 AND u.company_id=? AND u.active=1 AND u.role IN (?,?) AND own.user_id=? ORDER BY u.name',[$patientId,$this->care->user['company_id'],'doctor','nurse',$this->care->user['id']]);
    }
    public function queue(array $filter=[]): array
    {
        $this->authorize(); $patients=$this->care->access->patients();$ids=array_column($patients,'id');
        $status=$filter['status']??'active'; if(!in_array($status,['active','open','in_progress','resolved','all'],true)) Input::fail('status');
        $deadline=$filter['deadline']??''; if(!in_array($deadline,['','overdue','today','next7'],true)) Input::fail('deadline');
        if(!in_array($filter['help_requested']??'',['','1'],true)) Input::fail('help_requested');
        if(!in_array($filter['contact_state']??'',['','lost'],true)) Input::fail('contact_state');
        foreach(['team_id','professional_id','service_unit_id','assigned_to'] as $key) if(isset($filter[$key]) && $filter[$key]!=='' && (!ctype_digit((string)$filter[$key]) || (int)$filter[$key]<1)) Input::fail($key);
        $rows=[];
        if($ids) {
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $rows=$this->care->store->all("SELECT i.*,p.name AS patient_name,t.name AS team_name,COALESCE(l.name,(SELECT su.name FROM appointments ap JOIN service_units su ON su.id=ap.service_unit_id WHERE ap.patient_id=i.patient_id AND ap.company_id=i.company_id ORDER BY ap.scheduled_at DESC LIMIT 1)) AS service_unit,u.name AS professional_name,a.name AS assigned_name FROM care_pending_items i JOIN patients p ON p.id=i.patient_id AND p.company_id=i.company_id LEFT JOIN teams t ON t.id=i.team_id AND t.company_id=i.company_id LEFT JOIN service_units l ON l.id=i.service_unit_id AND l.company_id=i.company_id LEFT JOIN users u ON u.id=i.professional_id AND u.company_id=i.company_id LEFT JOIN users a ON a.id=i.assigned_to AND a.company_id=i.company_id WHERE i.company_id=? AND i.patient_id IN ($marks)",[$this->care->user['company_id'],...$ids]);
        }
        if(LocalProfileAccess::publicDemo()){
            $outreach=new OutreachService($this->care->store,$this->care->user);$q=$outreach->queue();$hidden=array_column([...$q['items'],...$q['waiting']],'patient_id');foreach($outreach->people() as $p)if($p['registration']==='DEMO-0001')$hidden[]=$p['patient_id'];
            $rows=array_values(array_filter($rows,fn($r)=>!in_array($r['patient_id'],$hidden)));
        }
        $options=['teams'=>[],'professionals'=>[],'units'=>[]];
        foreach($rows as $row) foreach([['teams','team_id','team_name'],['professionals','professional_id','professional_name'],['units','service_unit_id','service_unit']] as [$group,$id,$name]) if($row[$id]) $options[$group][(int)$row[$id]]=['id'=>(int)$row[$id],'name'=>$row[$name]];
        foreach($options as &$option) $option=array_values($option); unset($option);
        $now=date('Y-m-d H:i:s');$today=date('Y-m-d');
        $rows=array_values(array_filter($rows,function($row)use($filter,$status,$deadline,$now,$today){
            if($status==='active' && $row['status']==='resolved') return false;
            if(!in_array($status,['active','all'],true) && $row['status']!==$status) return false;
            if(($filter['help_requested']??'')==='1' && !(int)$row['help_requested']) return false;
            if(($filter['contact_state']??'')==='lost') {
                $contact=$this->care->store->one('SELECT payload FROM care_pending_events WHERE item_id=? AND event_type=? ORDER BY id DESC LIMIT 1',[$row['id'],'contact']);
                $outcome=$contact?(json_decode(Crypto::decrypt($contact['payload']),true)['outcome']??null):null;
                if(!in_array($outcome,['no_answer','invalid_contact'],true)) return false;
            }
            foreach(['team_id','professional_id','service_unit_id','assigned_to'] as $key) if(!empty($filter[$key]) && (int)$row[$key] !== (int)$filter[$key]) return false;
            return match($deadline){'overdue'=>$row['due_at']<$now,'today'=>substr($row['due_at'],0,10)===$today,'next7'=>$row['due_at']>=$now && $row['due_at']<=date('Y-m-d',time()+7*86400).' 23:59:59',default=>true};
        }));
        // Administrative order only: deadline, explicit help, kind, then waiting time.
        usort($rows,fn($a,$b)=>strcmp($a['due_at'],$b['due_at']) ?: (int)$b['help_requested']<=>(int)$a['help_requested'] ?: strcmp($a['kind'],$b['kind']) ?: strcmp($a['created_at'],$b['created_at']) ?: (int)$a['id']<=>(int)$b['id']);
        $returns=[];
        foreach($rows as &$row) {
            $row=$this->shortcuts($row);
            $patientId=(int)$row['patient_id'];
            if(!array_key_exists($patientId,$returns)) $returns[$patientId]=$this->care->store->one('SELECT MIN(scheduled_at) next_at FROM appointments WHERE patient_id=? AND company_id=? AND status IN (?,?) AND scheduled_at>=?',[$patientId,$this->care->user['company_id'],'expected','confirmed',$now])['next_at']??null;
            $row['next_return_at']=$returns[$patientId];
            $contact=$this->care->store->one('SELECT payload FROM care_pending_events WHERE item_id=? AND event_type=? ORDER BY id DESC LIMIT 1',[$row['id'],'contact']);
            $row['last_contact_outcome']=$contact?(json_decode(Crypto::decrypt($contact['payload']),true)['outcome']??null):null;
            $row['overdue']=$row['due_at']<$now && $row['status']!=='resolved';
            $row['priority_reason']=($row['overdue']?'Prazo previsto já passou. ':'Prazo previsto: '.(new \DateTimeImmutable($row['due_at']))->format('d/m/Y').'. ').((int)$row['help_requested']?'Pedido expresso de apoio. ':'').'Ordem administrativa; não representa risco clínico.';
        } unset($row);
        Audit::record($this->care->store,$this->care->user,'worklist_read','nursing_worklist',0);
        return ['items'=>$rows,'filters'=>$options,'patients'=>$patients,'generated_at'=>$now,'metrics'=>['total'=>count($rows),'overdue'=>count(array_filter($rows,fn($r)=>$r['overdue'])),'help'=>count(array_filter($rows,fn($r)=>(int)$r['help_requested']===1 && $r['status']!=='resolved'))]];
    }
    public function detail(int $id): array
    {
        $item=$this->shortcuts($this->item($id));$s=$this->care->store;$patient=(int)$item['patient_id'];
        $item['professionals']=$this->professionals($patient);
        $item['history']=$s->all('SELECT e.*,u.name AS author_name FROM care_pending_events e JOIN users u ON u.id=e.author_id WHERE e.item_id=? ORDER BY e.created_at DESC,e.id DESC',[$id]);
        foreach($item['history'] as &$event) { $event['note']=Crypto::decrypt($event['note']);$event['payload']=json_decode(Crypto::decrypt($event['payload']),true); } unset($event);
        $item['summary']=(new PatientSummaryService($this->care))->summarize($patient);
        $item['appointments']=$s->all('SELECT a.id,a.scheduled_at,a.status,u.name AS professional_name,u.role AS professional_role FROM appointments a JOIN users u ON u.id=a.professional_id WHERE a.patient_id=? AND a.company_id=? ORDER BY a.scheduled_at DESC,a.id DESC',[$patient,$this->care->user['company_id']]);
        $item['cases']=[];
        foreach((new ContinuityService($this->care))->cases() as $case) if((int)$case['patient_id']===$patient) {
            $case['contacts']=(new ContinuityService($this->care))->detail((int)$case['id'])['actions'];$item['cases'][]=$case;
        }
        $item['next_activities']=$s->all('SELECT id,title,due_at,status FROM care_pending_items WHERE patient_id=? AND company_id=? AND status<>? ORDER BY due_at,id',[$patient,$this->care->user['company_id'],'resolved']);
        Audit::record($s,$this->care->user,'worklist_detail_read','care_pending_item',$id);return $item;
    }
    public function create(array $d): array
    {
        $this->authorize();$patient=Input::id($d,'patient_id');$this->care->access->patient($patient);
        $title=Input::text($d,'title',180);$due=Input::date($d,'due_date');$note=Input::text($d,'note',1000,true);
        $responsible=Input::id($d,'assigned_to');$team=Input::id($d,'team_id');$this->care->access->team($patient,$team,$responsible);
        $kind=Input::choice($d,'kind',['manual_task','reschedule_request','communication_failure','welcome_request']);
        return $this->care->store->transaction(function()use($patient,$title,$due,$note,$responsible,$team,$kind){
            $id=$this->care->store->insert('care_pending_items',['company_id'=>$this->care->user['company_id'],'patient_id'=>$patient,'source_type'=>'manual','source_id'=>null,'kind'=>$kind,'title'=>$title,'reason'=>'Tarefa criada pela equipe; consulte o histórico.','next_action'=>'Executar o próximo passo pactuado.','team_id'=>$team,'professional_id'=>$responsible,'assigned_to'=>$responsible,'due_at'=>$due.' 23:59:59','help_requested'=>(int)($kind==='welcome_request'),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            $this->event($id,'created',$note);return ['id'=>$id];
        });
    }
    public function action(int $id,array $d): array
    {
        $this->authorize();$version=Input::id($d,'version');
        $type=Input::choice($d,'type',['contact','assign','take','start','resolve','reopen','attendance','confirm','reschedule','difficulty','refer_doctor','nursing_record','schedule_return','record_return']);
        $note=Input::text($d,'note',1000,!in_array($type,['resolve','reopen','contact','refer_doctor','nursing_record'],true));
        return $this->care->store->transaction(function()use($id,$d,$version,$type,$note){
            $item=$this->item($id,true);$s=$this->care->store;
            if((int)$item['version']!==$version) throw new HttpException(409,'CONFLICT','Pendência atualizada. Reabra a ficha antes de registrar.');
            if($item['status']==='resolved' && $type!=='reopen') throw new HttpException(409,'ITEM_RESOLVED','Reabra a pendência antes de registrar ações.');
            if($type==='reopen' && $item['status']!=='resolved') throw new HttpException(409,'ITEM_OPEN','A pendência já está aberta.');
            $status=$item['status'];$assigned=$item['assigned_to'];$payload=[];
            if(in_array($type,['assign','take'],true)) {
                if($type==='take' && $assigned && (int)$assigned!==(int)$this->care->user['id']) throw new HttpException(409,'ALREADY_ASSIGNED','Caso já atribuído a outro profissional. Reabra a ficha para conferir.');
                $assigned=$type==='take'?(int)$this->care->user['id']:Input::id($d,'assigned_to');$eligible=array_filter($this->professionals((int)$item['patient_id']),fn($u)=>(int)$u['id']===$assigned && (!$item['team_id'] || (int)$u['team_id']===(int)$item['team_id']));
                if(!$eligible) Input::fail('profissional vinculado à equipe');$payload['assigned_to']=$assigned;
                if($type==='take') $status='in_progress';
            }
            if($type==='start') $status='in_progress';
            if($type==='resolve') {
                if($item['source_type']==='clinical_return') throw new HttpException(422,'ATTENDANCE_REQUIRED','O retorno médico permanece devido até um comparecimento posterior registrado.');
                $status='resolved';
            }
            if($type==='record_return') {
                if($item['source_type']!=='clinical_return') Input::fail('retorno médico');
                $r=$s->oneLocked('SELECT * FROM clinical_return_plans WHERE id=? AND company_id=? AND patient_id=?',[$item['source_id'],$this->care->user['company_id'],$item['patient_id']]);
                if(!$r || (string)$r['version']!==$item['source_revision']) throw new HttpException(409,'CONFLICT','Retorno médico alterado. Atualize a fila.');
                $appointment=Input::id($d,'appointment_id');
                $a=$s->oneLocked('SELECT a.* FROM appointments a JOIN users u ON u.id=a.professional_id AND u.company_id=a.company_id WHERE a.id=? AND a.company_id=? AND a.patient_id=? AND u.role=?',[$appointment,$this->care->user['company_id'],$item['patient_id'],'doctor']);
                if(!$a || !in_array($a['status'],['expected','confirmed'],true) || $a['scheduled_at']>date('Y-m-d H:i:s') || $a['scheduled_at']<=$r['assessment_date'].' 23:59:59') Input::fail('consulta médica posterior já ocorrida');
                $this->care->access->team((int)$item['patient_id'],(int)$r['team_id'],(int)$a['professional_id']);
                $case=$s->one('SELECT id,version FROM continuity_cases WHERE appointment_id=? AND company_id=? AND patient_id=?',[$appointment,$this->care->user['company_id'],$item['patient_id']]);
                if(!$case) { $caseId=$s->insert('continuity_cases',['company_id'=>$this->care->user['company_id'],'patient_id'=>$item['patient_id'],'appointment_id'=>$appointment,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);$case=['id'=>$caseId,'version'=>1]; }
                $payload['attendance']=(new ContinuityService($this->care))->action((int)$case['id'],['type'=>'attendance','version'=>(int)$case['version'],'administrative_note'=>$note]);$status='resolved';
            }
            if($type==='schedule_return') {
                if($item['source_type']!=='clinical_return') Input::fail('retorno médico');
                $currentReturn=$s->oneLocked('SELECT version FROM clinical_return_plans WHERE id=? AND company_id=? AND patient_id=?',[$item['source_id'],$this->care->user['company_id'],$item['patient_id']]);
                if(!$currentReturn || (string)$currentReturn['version']!==$item['source_revision']) throw new HttpException(409,'CONFLICT','Retorno médico alterado. Atualize a fila.');
                $team=Input::id($d,'team_id');$professional=Input::id($d,'professional_id');
                $eligible=$s->one('SELECT id FROM users WHERE id=? AND company_id=? AND role=? AND active=1',[$professional,$this->care->user['company_id'],'doctor']);
                if(!$eligible || (int)$item['team_id']!==$team) Input::fail('médico da equipe de referência');
                $payload['appointment']=(new ContinuityService($this->care))->schedule((int)$item['patient_id'],$d);
                $status='in_progress';
            }
            if($type==='reopen') $status='open';
            if($type==='contact') { $payload['channel']=Input::choice($d,'channel',['phone','in_person','other']);$payload['outcome']=Input::choice($d,'outcome',['reached','no_answer','invalid_contact']);$status='in_progress'; }
            if(in_array($type,['attendance','confirm','reschedule','difficulty'],true)) {
                if($item['source_type']!=='case') Input::fail('pendência de retorno');
                $continuity=new ContinuityService($this->care);$case=$continuity->detail((int)$item['source_id']);
                $input=['type'=>$type==='difficulty'?'barrier':$type,'version'=>(int)$case['version'],'administrative_note'=>$note];
                if($type==='difficulty') $input['barrier']=Input::choice($d,'barrier',['transport','schedule','contact','personal','declined']);
                if($type==='reschedule') { $input['date']=Input::date($d,'date');$input['time']=Input::text($d,'time',5); }
                $payload['continuity']=$continuity->action((int)$case['id'],$input);$status=in_array($type,['attendance','reschedule'],true)?'resolved':'in_progress';
            }
            if($type==='refer_doctor') {
                $team=Input::id($d,'team_id');$professional=Input::id($d,'professional_id');
                $eligible=array_filter($this->professionals((int)$item['patient_id']),fn($u)=>$u['role']==='doctor' && (int)$u['id']===$professional && (int)$u['team_id']===$team);
                if(!$eligible) Input::fail('médico vinculado');
                $payload['referral']=(new ContinuityService($this->care))->referral(['patient_id'=>(int)$item['patient_id'],'team_id'=>$team,'to_professional_id'=>$professional,'reason'=>$note]);$status='in_progress';
            }
            if($type==='nursing_record') {
                $this->care->access->roles(['nurse']);$planId=Input::id($d,'plan_id');$plan=$this->care->plan($planId);
                if((int)$plan['patient_id']!==(int)$item['patient_id'] || $plan['status']==='closed') Input::fail('plano ativo do paciente');
                $payload['evolution']=$this->care->evolution($planId,['kind'=>'nursing','notes'=>$note]);$status='in_progress';
            }
            $s->execute('UPDATE care_pending_items SET status=?,assigned_to=?,updated_at=?,version=version+1 WHERE id=?',[$status,$assigned,date('Y-m-d H:i:s'),$id]);
            $this->event($id,$type,$note,$payload+['before_status'=>$item['status'],'after_status'=>$status]);
            return ['id'=>$id,'version'=>$version+1];
        });
    }
}
