<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Input;
final class DashboardService
{
    public function __construct(private CareService $care) {}
    public function clinical(string $role,array $filter): array
    {
        $this->care->access->roles([$role]);
        [$patients,$ids,$from,$to]=$this->scope($filter);
        $empty=['patients'=>$patients,'appointments'=>[],'plans'=>[],'goals'=>[],'indicators'=>[],'measurements'=>[],'referrals'=>[],'notifications'=>[],'evolutions'=>[],'metrics'=>['patients'=>count($patients),'appointments_today'=>0,'active_plans'=>0,'goals_tracked'=>0,'measurements'=>0,'reviews_completed'=>0,'goals_attained'=>0,'actions_completed'=>0,'plans_revised'=>0,'reassessments_due'=>0]];
        if (!$ids) return $empty;
        $s=$this->care->store; $marks=implode(',',array_fill(0,count($ids),'?')); $company=$this->care->user['company_id'];
        $plans=array_values(array_filter($this->care->plans(),fn($p)=>in_array((int)$p['patient_id'],$ids,true)));
        $goals=[]; $indicators=[]; $measurements=[]; $engine=new IndicatorService($this->care);
        foreach($plans as $p) foreach($engine->goals((int)$p['id']) as $g) $goals[]=$g+['patient_id'=>$p['patient_id']];
        foreach($ids as $patient) foreach($engine->list($patient) as $i) {
            $indicators[]=$i;
            foreach($s->all('SELECT * FROM indicator_measurements WHERE indicator_id=? AND collected_at>=? AND collected_at<=? ORDER BY collected_at,id',[$i['id'],$from.' 00:00:00',$to.' 23:59:59']) as $m) $measurements[]=$m;
        }
        $appointments=$s->all("SELECT a.*,p.name AS patient_name FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.company_id=? AND a.patient_id IN ($marks) AND a.scheduled_at>=? AND a.scheduled_at<=? ORDER BY a.scheduled_at",[$company,...$ids,$from.' 00:00:00',$to.' 23:59:59']);
        $referrals=$s->all("SELECT r.*,u.name AS recipient_name FROM referrals r JOIN users u ON u.id=r.to_professional_id WHERE r.company_id=? AND r.patient_id IN ($marks) ORDER BY r.created_at DESC",[$company,...$ids]);
        foreach($referrals as &$r) $r['reason']=Crypto::decrypt($r['reason']); unset($r);
        $notifications=$s->all("SELECT * FROM team_notifications WHERE company_id=? AND patient_id IN ($marks) AND created_at>=? AND created_at<=? ORDER BY id DESC",[$company,...$ids,$from.' 00:00:00',$to.' 23:59:59']);
        $evolutions=[]; $reviews=0; $revised=0; $completed=0;
        foreach($plans as $p) {
            $range=[$p['id'],$from.' 00:00:00',$to.' 23:59:59'];
            $n=(int)$s->one('SELECT COUNT(*) n FROM care_plan_reviews WHERE plan_id=? AND created_at>=? AND created_at<=?',$range)['n']; $reviews+=$n; if($n) $revised++;
            $completed+=(int)$s->one('SELECT COUNT(*) n FROM care_plan_adherence WHERE plan_id=? AND completed=1 AND occurred_at>=? AND occurred_at<=?',$range)['n'];
            foreach($s->all('SELECT * FROM clinical_evolutions WHERE plan_id=? AND created_at>=? AND created_at<=? ORDER BY created_at DESC',$range) as $e) { $e['notes']=Crypto::decrypt($e['notes']); $evolutions[]=$e; }
        }
        Audit::record($s,$this->care->user,'dashboard_read','clinical_dashboard',0);
        return ['patients'=>$patients,'appointments'=>$appointments,'plans'=>$plans,'goals'=>$goals,'indicators'=>$indicators,'measurements'=>$measurements,'referrals'=>$referrals,'notifications'=>$notifications,'evolutions'=>$evolutions,'metrics'=>[
            'patients'=>count($patients),'appointments_today'=>count(array_filter($appointments,fn($a)=>substr($a['scheduled_at'],0,10)===date('Y-m-d') && (int)$a['professional_id']===(int)$this->care->user['id'])),
            'active_plans'=>count(array_filter($plans,fn($p)=>$p['status']==='active')),
            'goals_tracked'=>count(array_filter($goals,fn($g)=>$g['status']==='active')),'measurements'=>count($measurements),'reviews_completed'=>$reviews,
            'goals_attained'=>count(array_filter($goals,fn($g)=>$g['progress']['state']==='attained')),'actions_completed'=>$completed,'plans_revised'=>$revised,
            'reassessments_due'=>count(array_filter($plans,fn($p)=>$p['status']!=='closed' && $p['reassessment_date']>=$from && $p['reassessment_date']<=$to)),
        ]];
    }
    private function scope(array $filter): array
    {
        $patients=$this->care->access->patients();
        if (isset($filter['patient_id']) && $filter['patient_id']!=='') {
            if (!ctype_digit((string)$filter['patient_id']) || (int)$filter['patient_id']<1) Input::fail('patient_id');
            $id=(int)$filter['patient_id']; $this->care->access->patient($id,false); $patients=array_values(array_filter($patients,fn($p)=>(int)$p['id']===$id));
        }
        $from=Input::date(['from'=>$filter['from']??date('Y-m-01')],'from');
        $to=Input::date(['to'=>$filter['to']??date('Y-m-t')],'to');
        if($from>$to || (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days>366) Input::fail('intervalo máximo de 366 dias');
        return [$patients,array_map('intval',array_column($patients,'id')),$from,$to];
    }
    public function continuity(array $filter): array
    {
        [$patients,$ids,$from,$to]=$this->scope($filter);
        $cases=array_values(array_filter((new ContinuityService($this->care))->cases(),fn($c)=>in_array((int)$c['patient_id'],$ids,true) && substr($c['scheduled_at'],0,10)>=$from && substr($c['scheduled_at'],0,10)<=$to));
        $appointments=[];
        foreach($ids as $id) foreach($this->care->store->all('SELECT status FROM appointments WHERE company_id=? AND patient_id=? AND scheduled_at>=? AND scheduled_at<=?',[$this->care->user['company_id'],$id,$from.' 00:00:00',$to.' 23:59:59']) as $a) $appointments[]=$a;
        $eligible=count(array_filter($appointments,fn($a)=>!in_array($a['status'],['cancelled','rescheduled'],true)));
        $confirmed=count(array_filter($appointments,fn($a)=>in_array($a['status'],['confirmed','attended'],true)));
        $metrics=['returns_expected'=>$eligible,'returns_confirmed'=>$confirmed,'pending'=>count(array_filter($cases,fn($c)=>$c['status']==='pending')),'rescheduled'=>count(array_filter($cases,fn($c)=>$c['status']==='rescheduled')),'reconnected'=>count(array_filter($cases,fn($c)=>$c['status']==='reconnected')),'barriers_resolved'=>count(array_filter($cases,fn($c)=>(int)$c['barrier_resolved']===1)),'continuity_rate'=>$eligible?round(100*$confirmed/$eligible,1):null];
        return ['metrics'=>$metrics,'cases'=>$cases,'patients'=>$patients];
    }
    public function portal(): array
    {
        $u=$this->care->user; $s=$this->care->store;
        if(in_array($u['role'],['worker','dependent'],true)) {
            return ['appointments'=>$s->all('SELECT a.id,a.scheduled_at,a.status FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE p.user_id=? AND p.company_id=? AND a.company_id=p.company_id ORDER BY a.scheduled_at',[$u['id'],$u['company_id']])];
        }
        $this->care->access->roles(['admin','manager']);
        $n=(int)$s->one('SELECT COUNT(*) n FROM patients WHERE company_id=?',[$u['company_id']])['n'];
        // Small groups are suppressed to reduce re-identification in employer views.
        if($n<10) return ['suppressed'=>true,'message'=>'Indicadores empresariais disponíveis somente para grupos com pelo menos dez participantes.'];
        $rows=$s->all('SELECT status,COUNT(*) n FROM appointments WHERE company_id=? GROUP BY status',[$u['company_id']]);
        foreach($rows as &$row) if((int)$row['n']<10) $row['n']=null; unset($row);
        Audit::record($s,$u,'aggregate_read','company', (int)$u['company_id']);
        return ['suppressed'=>false,'participants'=>$n,'appointment_totals'=>$rows];
    }
}
