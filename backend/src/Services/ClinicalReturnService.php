<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};

final class ClinicalReturnService
{
    public function __construct(private readonly CareService $care) {}
    private function row(int $patient): ?array
    {
        return $this->care->store->one('SELECT r.*,u.name AS doctor_name,t.name AS team_name FROM clinical_return_plans r JOIN users u ON u.id=r.doctor_id JOIN teams t ON t.id=r.team_id WHERE r.patient_id=? AND r.company_id=?',[$patient,$this->care->user['company_id']]);
    }
    private function attendance(array $row): ?array
    {
        // Same-day consultation belongs to the assessment, not the subsequent return.
        return $this->care->store->one('SELECT a.id,a.scheduled_at,u.name AS professional_name FROM appointments a JOIN users u ON u.id=a.professional_id AND u.company_id=a.company_id WHERE a.patient_id=? AND a.company_id=? AND a.status=? AND a.scheduled_at>? AND a.scheduled_at<=? AND u.role=? AND (a.professional_id=? OR EXISTS (SELECT 1 FROM team_members m JOIN teams t ON t.id=m.team_id WHERE m.user_id=a.professional_id AND m.team_id=? AND t.company_id=a.company_id)) ORDER BY a.scheduled_at,a.id LIMIT 1',[$row['patient_id'],$row['company_id'],'attended',$row['assessment_date'].' 23:59:59',date('Y-m-d H:i:s'),'doctor',$row['doctor_id'],$row['team_id']]);
    }
    public function view(int $patient,bool $history=true): array
    {
        $this->care->access->patient($patient);$row=$this->row($patient);
        if(!$row) return ['available'=>false,'version'=>0];
        $attendance=$this->attendance($row);
        $result=['available'=>true,'id'=>(int)$row['id'],'version'=>(int)$row['version'],'risk'=>(int)Crypto::decrypt($row['risk_cipher']),'assessment_date'=>$row['assessment_date'],'due_date'=>$row['due_date'],'team_id'=>(int)$row['team_id'],'team_name'=>$row['team_name'],'doctor_id'=>(int)$row['doctor_id'],'doctor_name'=>$row['doctor_name'],'updated_at'=>$row['updated_at'],'attendance'=>$attendance,'status'=>$attendance?'completed':($row['due_date']<date('Y-m-d')?'overdue':($row['due_date']===date('Y-m-d')?'today':'upcoming'))];
        if($history) {
            $result['history']=$this->care->store->all('SELECT e.id,e.version,e.event_type,e.payload,e.created_at,u.name AS author_name FROM clinical_return_events e JOIN users u ON u.id=e.author_id WHERE e.return_plan_id=? ORDER BY e.version DESC LIMIT 20',[$row['id']]);
            foreach($result['history'] as &$event) $event['payload']=json_decode(Crypto::decrypt($event['payload']),true,32,JSON_THROW_ON_ERROR);unset($event);
            Audit::record($this->care->store,$this->care->user,'clinical_return_read','patient',$patient);
        }
        return $result;
    }
    public function save(int $patient,array $d): array
    {
        $this->care->access->roles(['doctor']);$this->care->access->patient($patient);
        $risk=Input::id($d,'risk');if($risk>4) Input::fail('risco confirmado de 1 a 4');
        $due=Input::date($d,'due_date');$assessed=Input::date($d+['assessment_date'=>date('Y-m-d')],'assessment_date');
        if($assessed>date('Y-m-d') || $due<=$assessed) Input::fail('data do retorno posterior à avaliação');
        $team=Input::id($d,'team_id');$this->care->access->team($patient,$team,(int)$this->care->user['id']);
        $version=$d['version']??null;if(!is_int($version) || $version<0) Input::fail('versão');
        $reason=Input::text($d,'reason',1000,true);$s=$this->care->store;
        return $s->transaction(function()use($s,$patient,$risk,$due,$assessed,$team,$version,$reason){
            // Serializes initial creation as well as later revisions.
            $s->oneLocked('SELECT id FROM patients WHERE id=? AND company_id=?',[$patient,$this->care->user['company_id']]);
            $this->care->access->team($patient,$team,(int)$this->care->user['id']);
            $old=$this->row($patient);
            if(($old?(int)$old['version']:0)!==$version) throw new HttpException(409,'CONFLICT','Risco ou retorno alterado por outro profissional. Atualize antes de salvar.');
            $previous=$old?['risk'=>(int)Crypto::decrypt($old['risk_cipher']),'due_date'=>$old['due_date'],'assessment_date'=>$old['assessment_date'],'team_id'=>(int)$old['team_id'],'doctor_id'=>(int)$old['doctor_id']]:null;
            if($previous && $risk<$previous['risk'] && $reason==='') Input::fail('justificativa do rebaixamento do risco');
            $now=date('Y-m-d H:i:s');$values=['team_id'=>$team,'doctor_id'=>$this->care->user['id'],'risk_cipher'=>Crypto::encrypt((string)$risk),'assessment_date'=>$assessed,'due_date'=>$due,'version'=>$version+1,'updated_at'=>$now];
            if(!$old) $id=$s->insert('clinical_return_plans',['company_id'=>$this->care->user['company_id'],'patient_id'=>$patient,'created_at'=>$now]+$values);
            else {
                $id=(int)$old['id'];
                $s->execute('UPDATE clinical_return_plans SET team_id=?,doctor_id=?,risk_cipher=?,assessment_date=?,due_date=?,version=?,updated_at=? WHERE id=?',[...array_values($values),$id]);
            }
            $type=$previous && $risk<$previous['risk']?'risk_downgraded':($old?'return_revised':'risk_confirmed');
            $after=['risk'=>$risk,'due_date'=>$due,'assessment_date'=>$assessed,'team_id'=>$team,'doctor_id'=>(int)$this->care->user['id']];
            $s->insert('clinical_return_events',['return_plan_id'=>$id,'author_id'=>$this->care->user['id'],'version'=>$version+1,'event_type'=>$type,'payload'=>Crypto::encrypt(json_encode(['before'=>$previous,'after'=>$after,'reason'=>$reason],JSON_THROW_ON_ERROR)),'created_at'=>$now]);
            Audit::record($s,$this->care->user,$type,'clinical_return_plan',$id);
            return $this->view($patient);
        });
    }
}
