<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\HttpException;

final class DoctorReactivationService
{
    public function __construct(private readonly CareService $care) {}
    private function authorize(): void { $this->care->access->roles(['doctor']); }
    private function policy(bool $lock=false): ?array
    {
        $sql='SELECT gamification_enabled,reactivation_enabled,reactivation_days,reactivation_points,reactivation_policy_at FROM companies WHERE id=?';
        if($lock && $this->care->store->db->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql') $sql.=' LOCK IN SHARE MODE';
        return $this->care->store->one($sql,[$this->care->user['company_id']]);
    }
    private function enabled(?array $policy): bool { return $policy && (int)$policy['gamification_enabled']===1 && (int)$policy['reactivation_enabled']===1 && $policy['reactivation_policy_at']!==null; }
    private function patients(): array
    {
        $patients=$this->care->access->patients();
        return array_values(array_filter($patients,fn($p)=>$this->care->store->one('SELECT p.id FROM patients p LEFT JOIN users u ON u.id=p.user_id LEFT JOIN beneficiary_links b ON b.patient_id=p.id AND b.company_id=p.company_id WHERE p.id=? AND p.company_id=? AND (u.role=? OR b.kind=?)',[$p['id'],$this->care->user['company_id'],'worker','worker'])!==null));
    }
    private function activities(int $patient): array
    {
        $s=$this->care->store;$scope=[$patient,$this->care->user['company_id']];$now=date('Y-m-d H:i:s');$events=[];
        foreach($s->all('SELECT a.id,a.scheduled_at AS occurred_at,a.professional_id,u.role FROM appointments a JOIN users u ON u.id=a.professional_id AND u.company_id=a.company_id WHERE a.patient_id=? AND a.company_id=? AND a.status=? AND a.scheduled_at<=? AND u.role IN (?,?)',[...$scope,'attended',$now,'doctor','nurse']) as $row) $events[]=$row+['type'=>'appointment'];
        foreach(['evolution'=>'clinical_evolutions','review'=>'care_plan_reviews'] as $type=>$table) foreach($s->all("SELECT e.id,e.created_at AS occurred_at,e.author_id AS professional_id,u.role FROM $table e JOIN care_plans p ON p.id=e.plan_id JOIN users u ON u.id=e.author_id AND u.company_id=p.company_id WHERE p.patient_id=? AND p.company_id=? AND e.created_at<=? AND p.created_at<=e.created_at AND p.start_date<=SUBSTR(e.created_at,1,10) AND u.role IN (?,?)",[...$scope,$now,'doctor','nurse']) as $row) $events[]=$row+['type'=>$type];
        $order=['appointment'=>0,'evolution'=>1,'review'=>2];
        usort($events,fn($a,$b)=>strcmp($a['occurred_at'],$b['occurred_at']) ?: $order[$a['type']]<=>$order[$b['type']] ?: (int)$a['id']<=>(int)$b['id']);
        return $events;
    }
    private function pairs(array $events): array
    {
        $pairs=[];$previous=null;
        foreach($events as $event) {
            if($previous) $pairs[$event['type'].':'.$event['id']]=['baseline'=>$previous,'return'=>$event,'gap_days'=>(int)floor((strtotime($event['occurred_at'])-strtotime($previous['occurred_at']))/86400)];
            $previous=$event;
        } return $pairs;
    }
    public function sync(): array
    {
        $this->authorize();$s=$this->care->store;
        return $s->transaction(function()use($s){
            $policy=$this->policy(true);if(!$this->enabled($policy)) return ['enabled'=>false];
            foreach($this->patients() as $patient) {
                $s->oneLocked('SELECT id FROM patients WHERE id=? AND company_id=?',[$patient['id'],$this->care->user['company_id']]);
                $pairs=$this->pairs($this->activities((int)$patient['id']));
                foreach($s->all('SELECT * FROM doctor_reactivation_awards WHERE company_id=? AND patient_id=? AND professional_id=?',[$this->care->user['company_id'],$patient['id'],$this->care->user['id']]) as $award) {
                    $pair=$pairs[$award['return_type'].':'.$award['return_id']]??null;
                    $valid=$pair && $pair['return']['occurred_at']>=$award['policy_at'] && $pair['baseline']['type']===$award['baseline_type'] && (int)$pair['baseline']['id']===(int)$award['baseline_id'] && (int)$pair['return']['professional_id']===(int)$this->care->user['id'] && $pair['gap_days']>=(int)$award['threshold_days'];
                    $status=$valid?'active':'corrected';
                    if($status!==$award['status']) { $s->execute('UPDATE doctor_reactivation_awards SET status=? WHERE id=?',[$status,$award['id']]);Audit::record($s,$this->care->user,'reactivation_evidence_corrected','doctor_reactivation_award',(int)$award['id']); }
                }
                foreach($pairs as $pair) {
                    $event=$pair['return'];$baseline=$pair['baseline'];
                    if($event['role']!=='doctor' || (int)$event['professional_id']!==(int)$this->care->user['id'] || $event['occurred_at']<$policy['reactivation_policy_at'] || $pair['gap_days']<(int)$policy['reactivation_days']) continue;
                    if($s->one('SELECT id FROM doctor_reactivation_awards WHERE company_id=? AND patient_id=? AND baseline_type=? AND baseline_id=?',[$this->care->user['company_id'],$patient['id'],$baseline['type'],$baseline['id']])) continue;
                    $id=$s->insert('doctor_reactivation_awards',['company_id'=>$this->care->user['company_id'],'patient_id'=>$patient['id'],'professional_id'=>$this->care->user['id'],'baseline_type'=>$baseline['type'],'baseline_id'=>$baseline['id'],'return_type'=>$event['type'],'return_id'=>$event['id'],'gap_days'=>$pair['gap_days'],'threshold_days'=>$policy['reactivation_days'],'points'=>$policy['reactivation_points'],'policy_at'=>$policy['reactivation_policy_at'],'earned_at'=>$event['occurred_at']]);
                    Audit::record($s,$this->care->user,'reactivation_awarded','doctor_reactivation_award',$id);
                }
            }
            return $this->view();
        });
    }
    public function view(): array
    {
        $this->authorize();$policy=$this->policy();if(!$this->enabled($policy)) return ['enabled'=>false];
        $items=[];$opportunities=[];$total=0;$count=0;$now=time();
        foreach($this->patients() as $patient) {
            $events=$this->activities((int)$patient['id']);$pairs=$this->pairs($events);
            foreach($this->care->store->all('SELECT * FROM doctor_reactivation_awards WHERE company_id=? AND patient_id=? AND professional_id=? AND status=?',[$this->care->user['company_id'],$patient['id'],$this->care->user['id'],'active']) as $award) {
                $pair=$pairs[$award['return_type'].':'.$award['return_id']]??null;
                if(!$pair || $pair['return']['occurred_at']<$award['policy_at'] || (int)$pair['return']['professional_id']!==(int)$this->care->user['id'] || $pair['return']['role']!=='doctor' || $pair['baseline']['type']!==$award['baseline_type'] || (int)$pair['baseline']['id']!==(int)$award['baseline_id'] || $pair['gap_days']<(int)$award['threshold_days']) continue;
                $total+=(int)$award['points'];$count++;
                $items[]=['patient_id'=>(int)$patient['id'],'patient_name'=>$patient['name'],'gap_days'=>$pair['gap_days'],'points'=>(int)$award['points'],'earned_at'=>$award['earned_at']];
            }
            $last=$events?end($events):null;
            if($last && floor(($now-strtotime($last['occurred_at']))/86400)>=(int)$policy['reactivation_days']) $opportunities[]=['patient_id'=>(int)$patient['id'],'patient_name'=>$patient['name'],'last_recorded_at'=>$last['occurred_at'],'days_without_record'=>(int)floor(($now-strtotime($last['occurred_at']))/86400)];
        }
        usort($items,fn($a,$b)=>strcmp($b['earned_at'],$a['earned_at']));usort($opportunities,fn($a,$b)=>$b['days_without_record']<=>$a['days_without_record']);
        Audit::record($this->care->store,$this->care->user,'reactivation_read','professional',(int)$this->care->user['id']);
        return ['enabled'=>true,'threshold_days'=>(int)$policy['reactivation_days'],'bonus_points'=>(int)$policy['reactivation_points'],'points'=>$total,'reactivations'=>$count,'items'=>array_slice($items,0,5),'opportunities'=>array_slice($opportunities,0,3)];
    }
}
