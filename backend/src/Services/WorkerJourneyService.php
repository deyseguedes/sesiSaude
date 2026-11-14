<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\HttpException;
use App\Repositories\Store;

final class WorkerJourneyService
{
    private const RULES=[
        'scheduled'=>['title'=>'Consulta marcada','points'=>10,'description'=>'Uma consulta registrada na plataforma. Reagendar não gera pontos extras.'],
        'attended'=>['title'=>'Presença confirmada','points'=>20,'description'=>'Comparecimento registrado pela equipe após o horário da consulta.'],
        'followup'=>['title'=>'Cuidado em continuidade','points'=>30,'description'=>'Sua equipe registrou uma evolução ou revisão do plano em outro dia, após o primeiro comparecimento.'],
    ];
    public function __construct(private readonly Store $store,private readonly array $user) {}
    private function patient(): ?array
    {
        if($this->user['role']!=='worker') throw new HttpException(403,'FORBIDDEN','Jornada disponível somente no espaço individual do trabalhador.');
        // Exact account ownership; represented dependents never contribute to this score.
        return $this->store->one('SELECT id FROM patients WHERE user_id=? AND company_id=?',[$this->user['id'],$this->user['company_id']]);
    }
    private function evidence(int $patient): array
    {
        $scope=[$patient,$this->user['company_id']];$now=date('Y-m-d H:i:s');
        $booking=$this->store->one('SELECT id,scheduled_at FROM appointments WHERE patient_id=? AND company_id=? ORDER BY id LIMIT 1',$scope);
        $attendance=$this->store->one('SELECT id,scheduled_at FROM appointments WHERE patient_id=? AND company_id=? AND status=? AND scheduled_at<=? ORDER BY scheduled_at,id LIMIT 1',[...$scope,'attended',$now]);
        $result=['scheduled'=>$booking?['source_type'=>'appointment','source_id'=>(int)$booking['id'],'evidence_at'=>$booking['scheduled_at']]:null,'attended'=>$attendance?['source_type'=>'appointment','source_id'=>(int)$attendance['id'],'evidence_at'=>$attendance['scheduled_at']]:null,'followup'=>null];
        if(!$attendance) return $result;
        $events=[];
        foreach(['evolution'=>'clinical_evolutions','review'=>'care_plan_reviews'] as $type=>$table) {
            $row=$this->store->one("SELECT e.id,e.created_at FROM $table e JOIN care_plans p ON p.id=e.plan_id JOIN users u ON u.id=e.author_id AND u.company_id=p.company_id WHERE p.patient_id=? AND p.company_id=? AND e.created_at>? AND e.created_at<=? AND SUBSTR(e.created_at,1,10)>? AND p.created_at<=e.created_at AND p.start_date<=SUBSTR(e.created_at,1,10) AND u.role IN (?,?) ORDER BY e.created_at,e.id LIMIT 1",[...$scope,$attendance['scheduled_at'],$now,substr($attendance['scheduled_at'],0,10),'doctor','nurse']);
            if($row) $events[]=['source_type'=>$type,'source_id'=>(int)$row['id'],'evidence_at'=>$row['created_at']];
        }
        usort($events,fn($a,$b)=>strcmp($a['evidence_at'],$b['evidence_at']) ?: $a['source_id']<=>$b['source_id']);
        $result['followup']=$events[0]??null;return $result;
    }
    public function sync(): array
    {
        $patient=$this->patient();
        return $this->store->transaction(function()use($patient){
            // Shared policy lock permits different workers concurrently, while adoption edits wait.
            $policySql='SELECT gamification_enabled FROM companies WHERE id=?';
            if($this->store->db->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql') $policySql.=' LOCK IN SHARE MODE';
            $company=$this->store->one($policySql,[$this->user['company_id']]);
            if(!$company || !(int)$company['gamification_enabled'] || !$patient) return $this->view();
            // Serialize this person's recognition; repeating refreshes cannot multiply points.
            $this->store->oneLocked('SELECT id FROM patients WHERE id=? AND company_id=?',[$patient['id'],$this->user['company_id']]);
            $evidence=$this->evidence((int)$patient['id']);
            foreach(self::RULES as $code=>$rule) {
                $old=$this->store->one('SELECT * FROM worker_journey_awards WHERE patient_id=? AND company_id=? AND milestone=?',[$patient['id'],$this->user['company_id'],$code]);
                $source=$evidence[$code];
                if(!$source) {
                    if($old && $old['status']==='active') {
                        $this->store->execute('UPDATE worker_journey_awards SET status=?,version=version+1 WHERE id=?',['corrected',$old['id']]);
                        Audit::record($this->store,$this->user,'journey_evidence_corrected','worker_journey_award',(int)$old['id']);
                    } continue;
                }
                if(!$old) {
                    $id=$this->store->insert('worker_journey_awards',['company_id'=>$this->user['company_id'],'patient_id'=>$patient['id'],'milestone'=>$code,'points'=>$rule['points'],...$source,'earned_at'=>date('Y-m-d H:i:s')]);
                    Audit::record($this->store,$this->user,'journey_awarded','worker_journey_award',$id);
                } elseif($old['status']!=='active' || $old['source_type']!==$source['source_type'] || (int)$old['source_id']!==$source['source_id'] || $old['evidence_at']!==$source['evidence_at'] || (int)$old['points']!==$rule['points']) {
                    $this->store->execute('UPDATE worker_journey_awards SET status=?,points=?,source_type=?,source_id=?,evidence_at=?,version=version+1 WHERE id=?',['active',$rule['points'],$source['source_type'],$source['source_id'],$source['evidence_at'],$old['id']]);
                    Audit::record($this->store,$this->user,'journey_reconciled','worker_journey_award',(int)$old['id']);
                }
            }
            return $this->view();
        });
    }
    public function view(): array
    {
        $patient=$this->patient();
        $company=$this->store->one('SELECT gamification_enabled FROM companies WHERE id=?',[$this->user['company_id']]);
        if(!$company || !(int)$company['gamification_enabled']) return ['enabled'=>false,'available'=>false];
        $evidence=$patient?$this->evidence((int)$patient['id']):[];
        $awards=$patient?$this->store->all('SELECT milestone,status,earned_at FROM worker_journey_awards WHERE company_id=? AND patient_id=?',[$this->user['company_id'],$patient['id']]):[];
        $byCode=array_column($awards,null,'milestone');$missions=[];$points=0;$completed=0;
        foreach(self::RULES as $code=>$rule) {
            $award=$byCode[$code]??null;
            $done=$award && $award['status']==='active' && !empty($evidence[$code]);
            if($done) { $points+=$rule['points'];$completed++; }
            $missions[]=['code'=>$code,...$rule,'completed'=>$done,'recognized_at'=>$done?$award['earned_at']:null];
        }
        $levels=[['name'=>'Começando minha jornada','minimum'=>0],['name'=>'Primeiro passo','minimum'=>10],['name'=>'Presença que conta','minimum'=>30],['name'=>'Cuidado em continuidade','minimum'=>60]];
        $level=$levels[0];$next=null;
        foreach($levels as $candidate) { if($points>=$candidate['minimum']) $level=$candidate; else { $next=$candidate;break; } }
        Audit::record($this->store,$this->user,'journey_read','patient',(int)($patient['id']??0));
        return ['enabled'=>true,'available'=>(bool)$patient,'points'=>$points,'maximum_points'=>60,'completed'=>$completed,'total'=>3,'level'=>$level,'next_level'=>$next,'missions'=>$missions,'updated_at'=>date('Y-m-d H:i:s')];
    }
}
