<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
final class ContinuityService
{
    public function __construct(private CareService $care) {}
    public function sync(): array
    {
        $patients=$this->care->access->patients(); $created=0;
        foreach($patients as $patient) {
            $rows=$this->care->store->all('SELECT a.* FROM appointments a WHERE a.company_id=? AND a.patient_id=? AND a.status=? AND NOT EXISTS (SELECT 1 FROM continuity_cases c WHERE c.appointment_id=a.id)',[$this->care->user['company_id'],$patient['id'],'expected']);
            foreach($rows as $appointment) {
                try {
                    $this->care->store->transaction(function()use($appointment){
                        $id=$this->care->store->insert('continuity_cases',['company_id'=>$appointment['company_id'],'patient_id'=>$appointment['patient_id'],'appointment_id'=>$appointment['id'],'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
                        Audit::record($this->care->store,$this->care->user,'case_created','continuity_case',$id);
                    }); $created++;
                } catch(\PDOException $e) {
                    // Another synchronization may have inserted the same appointment.
                    if (!$this->care->store->one('SELECT id FROM continuity_cases WHERE appointment_id=?',[$appointment['id']])) throw $e;
                }
            }
        }
        return ['created'=>$created];
    }
    public function cases(): array
    {
        $patients=$this->care->access->patients(); $ids=array_column($patients,'id'); if (!$ids) return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));
        return $this->care->store->all("SELECT c.*,p.name AS patient_name,a.scheduled_at,a.status AS appointment_status FROM continuity_cases c JOIN patients p ON p.id=c.patient_id JOIN appointments a ON a.id=c.appointment_id WHERE c.company_id=? AND c.patient_id IN ($marks) ORDER BY a.scheduled_at,c.id",[$this->care->user['company_id'],...$ids]);
    }
    public function detail(int $id): array
    {
        $this->care->access->roles(['doctor','nurse','navigator']);
        $row=$this->care->store->one('SELECT * FROM continuity_cases WHERE id=? AND company_id=?',[$id,$this->care->user['company_id']]);
        if (!$row) throw new HttpException(404,'NOT_FOUND','Caso não encontrado.');
        $this->care->access->patient((int)$row['patient_id'],false);
        $row['actions']=$this->care->store->all('SELECT * FROM continuity_actions WHERE case_id=? ORDER BY created_at,id',[$id]);
        $row['navigators']=$this->care->store->all('SELECT DISTINCT u.id,u.name FROM users u JOIN team_members m ON m.user_id=u.id JOIN teams t ON t.id=m.team_id AND t.company_id=u.company_id JOIN care_team_assignments a ON a.team_id=m.team_id AND a.company_id=u.company_id WHERE u.company_id=? AND u.role=? AND u.active=1 AND a.patient_id=? AND a.active=1 ORDER BY u.name',[$this->care->user['company_id'],'navigator',$row['patient_id']]);
        foreach($row['actions'] as &$a) { $a['administrative_note']=Crypto::decrypt($a['administrative_note']); $a['payload']=json_decode($a['payload'],true); }
        Audit::record($this->care->store,$this->care->user,'read','continuity_case',$id); return $row;
    }
    public function action(int $id,array $d): array
    {
        $case=$this->detail($id); $type=Input::choice($d,'type',['confirm','attendance','barrier','welcome','assign_navigator','reschedule','reconnect','resolve_barrier','clinical_review']);
        $version=Input::id($d,'version');
        if (in_array($case['status'],['attended','rescheduled'],true) && !in_array($type,['welcome','resolve_barrier','clinical_review'],true)) throw new HttpException(409,'CASE_CLOSED','Caso concluído; consulte o retorno vigente.');
        if ($type==='attendance') {
            $appointment=$this->care->store->one('SELECT scheduled_at FROM appointments WHERE id=?',[$case['appointment_id']]);
            if($appointment['scheduled_at']>date('Y-m-d H:i:s')) Input::fail('comparecimento anterior ao horário previsto');
        }
        $note=Crypto::encrypt(Input::text($d,'administrative_note',1000,true)); $payload=[];
        if ($type==='barrier') $payload['barrier']=Input::choice($d,'barrier',['transport','schedule','contact','personal','declined']);
        if ($type==='reschedule') {
            $date=Input::date($d,'date'); $time=Input::text($d,'time',5);
            if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D',$time) || $date.' '.$time.':00'<=date('Y-m-d H:i:s')) Input::fail('horário futuro');
            $payload['scheduled_at']=$date.' '.$time.':00';
        }
        if ($type==='assign_navigator') {
            $navigator=Input::id($d,'navigator_id');
            $u=$this->care->store->one('SELECT id FROM users WHERE id=? AND company_id=? AND role=? AND active=1',[$navigator,$this->care->user['company_id'],'navigator']);
            if (!$u) Input::fail('navigator_id');
            $a=new Access($this->care->store,['id'=>$navigator,'role'=>'navigator','company_id'=>$this->care->user['company_id']]); $a->patient((int)$case['patient_id'],false);
            $payload['navigator_id']=$navigator;
        }
        return $this->care->store->transaction(function()use($case,$id,$type,$version,$note,$payload){
            $s=$this->care->store;
            $q=$s->db->prepare('UPDATE continuity_cases SET version=version+1,updated_at=? WHERE id=? AND version=?'); $q->execute([date('Y-m-d H:i:s'),$id,$version]);
            if ($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Caso alterado. Recarregue antes de registrar.');
            if (in_array($type,['confirm','attendance','reconnect'],true)) {
                $status=match($type){'confirm'=>'confirmed','attendance'=>'attended','reconnect'=>'reconnected'};
                $s->execute('UPDATE continuity_cases SET status=? WHERE id=?',[$status,$id]);
                $s->execute('UPDATE appointments SET status=? WHERE id=?',[$type==='attendance'?'attended':'confirmed',$case['appointment_id']]);
            }
            if ($type==='barrier') $s->execute('UPDATE continuity_cases SET barrier=?,barrier_resolved=0 WHERE id=?',[$payload['barrier'],$id]);
            if ($type==='resolve_barrier') {
                if (!$case['barrier'] || $case['barrier']==='declined') Input::fail('barreira informada');
                $s->execute('UPDATE continuity_cases SET barrier_resolved=1 WHERE id=?',[$id]);
            }
            if ($type==='assign_navigator') $s->execute('UPDATE continuity_cases SET assigned_navigator_id=? WHERE id=?',[$payload['navigator_id'],$id]);
            if ($type==='reschedule') {
                $appointment=$s->one('SELECT * FROM appointments WHERE id=?',[$case['appointment_id']]);
                SchedulingService::lockProfessional($s,(int)$appointment['professional_id']);
                $scheduled=$payload['scheduled_at'];$end=date('Y-m-d H:i:s',strtotime($scheduled)+60);
                // An administrative reschedule reserves an instant; overlapping published slots are blocked.
                if($s->oneLocked('SELECT id FROM appointment_slots WHERE professional_id=? AND status=? AND starts_at<? AND ends_at>? AND appointment_id<>?',[$appointment['professional_id'],'reserved',$end,$scheduled,$appointment['id']]) || $s->oneLocked('SELECT id FROM appointments WHERE professional_id=? AND scheduled_at=? AND id<>? AND status NOT IN (?,?)',[$appointment['professional_id'],$scheduled,$appointment['id'],'rescheduled','cancelled'])) throw new HttpException(409,'SCHEDULE_CONFLICT','Profissional já possui atendimento neste horário.');
                $s->execute('UPDATE appointment_slots SET status=?,appointment_id=NULL,version=version+1 WHERE appointment_id=?',['available',$appointment['id']]);
                $s->execute('UPDATE appointment_slots SET status=?,version=version+1 WHERE professional_id=? AND status=? AND starts_at<? AND ends_at>?',['blocked',$appointment['professional_id'],'available',$end,$scheduled]);
                $new=$s->insert('appointments',['company_id'=>$case['company_id'],'patient_id'=>$case['patient_id'],'professional_id'=>$appointment['professional_id'],'scheduled_at'=>$scheduled,'status'=>'expected','modality'=>$appointment['modality'],'service_unit_id'=>$appointment['service_unit_id']]);
                $s->execute('UPDATE appointments SET status=? WHERE id=?',['rescheduled',$appointment['id']]);
                $s->execute('UPDATE continuity_cases SET status=?,rescheduled_appointment_id=? WHERE id=?',['rescheduled',$new,$id]);
                $s->insert('continuity_cases',['company_id'=>$case['company_id'],'patient_id'=>$case['patient_id'],'appointment_id'=>$new,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
                $payload['new_appointment_id']=$new;
            }
            $action=$s->insert('continuity_actions',['case_id'=>$id,'author_id'=>$this->care->user['id'],'action_type'=>$type,'administrative_note'=>$note,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'created_at'=>date('Y-m-d H:i:s')]);
            $s->insert('team_notifications',['company_id'=>$case['company_id'],'patient_id'=>$case['patient_id'],'case_id'=>$id,'event_type'=>$type,'created_at'=>date('Y-m-d H:i:s')]);
            Audit::record($s,$this->care->user,'continuity_'.$type,'continuity_case',$id); return ['id'=>$action,'version'=>$version+1];
        });
    }
    public function referral(array $d): array
    {
        $patient=Input::id($d,'patient_id'); $team=Input::id($d,'team_id'); $to=Input::id($d,'to_professional_id');
        $this->care->access->team($patient,$team,$to); $reason=Crypto::encrypt(Input::text($d,'reason',4000));
        return $this->care->store->transaction(function()use($patient,$to,$reason){
            $id=$this->care->store->insert('referrals',['company_id'=>$this->care->user['company_id'],'patient_id'=>$patient,'from_professional_id'=>$this->care->user['id'],'to_professional_id'=>$to,'reason'=>$reason,'created_at'=>date('Y-m-d H:i:s')]);
            Audit::record($this->care->store,$this->care->user,'referral_requested','referral',$id); return ['id'=>$id];
        });
    }
    public function schedule(int $patient,array $d): array
    {
        $team=Input::id($d,'team_id'); $professional=Input::id($d,'professional_id');
        $this->care->access->team($patient,$team,$professional);
        $date=Input::date($d,'date'); $time=Input::text($d,'time',5);
        if(!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D',$time) || $date.' '.$time.':00'<=date('Y-m-d H:i:s')) Input::fail('horário futuro');
        return $this->care->store->transaction(function()use($patient,$professional,$date,$time){
            $s=$this->care->store;SchedulingService::lockProfessional($s,$professional);$start=$date.' '.$time.':00';$end=date('Y-m-d H:i:s',strtotime($start)+60);
            if($s->oneLocked('SELECT id FROM appointment_slots WHERE professional_id=? AND status=? AND starts_at<? AND ends_at>?',[$professional,'reserved',$end,$start]) || $s->oneLocked('SELECT id FROM appointments WHERE professional_id=? AND scheduled_at=? AND status NOT IN (?,?)',[$professional,$start,'cancelled','rescheduled'])) throw new HttpException(409,'SCHEDULE_CONFLICT','Horário já ocupado.');
            $s->execute('UPDATE appointment_slots SET status=?,version=version+1 WHERE professional_id=? AND status=? AND starts_at<? AND ends_at>?',['blocked',$professional,'available',$end,$start]);
            $id=$this->care->store->insert('appointments',['company_id'=>$this->care->user['company_id'],'patient_id'=>$patient,'professional_id'=>$professional,'scheduled_at'=>$date.' '.$time.':00','status'=>'expected']);
            $case=$this->care->store->insert('continuity_cases',['company_id'=>$this->care->user['company_id'],'patient_id'=>$patient,'appointment_id'=>$id,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            Audit::record($this->care->store,$this->care->user,'return_scheduled','continuity_case',$case); return ['id'=>$id,'case_id'=>$case];
        });
    }
}
