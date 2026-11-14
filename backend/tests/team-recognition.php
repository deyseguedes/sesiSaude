<?php
declare(strict_types=1);
require __DIR__.'/company-gamification.php';
use App\Services\{TeamRecognitionService,DoctorReactivationService,CareService,IndustryService};
$case=['team_id'=>1,'due_count'=>320,'excluded_count'=>12,'on_time_count'=>169,'baseline_percent'=>46,'satisfaction_percent'=>95,'period'=>'quarter','frozen_verified'=>true,'baseline_verified'=>true,'no_upheld_pressure'=>true,'risk_audit_passed'=>true,'consent_compliant'=>true];
$r=TeamRecognitionService::calculate($case);
verify($r['denominator']===308 && $r['proposed_percent']===3 && $r['status']==='simulated','Original example produces 3 percent on 308 due');
foreach([[3.99,0],[4,1],[5.99,1],[6,2],[7.99,2],[8,3],[9.99,3],[10,4],[11.99,4],[12,5]] as [$gain,$band]) {
    $r=TeamRecognitionService::calculate(array_replace($case,['due_count'=>10000,'excluded_count'=>0,'on_time_count'=>(int)round((40+$gain)*100),'baseline_percent'=>40]));
    verify($r['proposed_percent']===$band,'Exact step '.$gain);
}
$r=TeamRecognitionService::calculate(array_replace($case,['due_count'=>300,'excluded_count'=>0,'on_time_count'=>240,'baseline_percent'=>85]));
verify($r['proposed_percent']===5,'Proposed excellence tier applies at 80 percent');
foreach(['frozen_verified','baseline_verified','no_upheld_pressure','risk_audit_passed','consent_compliant'] as $gate) {
    $r=TeamRecognitionService::calculate(array_replace($case,[$gate=>false]));
    verify($r['status']==='blocked' && count($r['blockers'])===1,'Each gate independently blocks '.$gate);
}
verify(TeamRecognitionService::calculate(array_replace($case,['satisfaction_percent'=>89.99]))['status']==='blocked','Satisfaction below 90 blocks');
foreach(['quarter'=>'Apurar no semestre.','semester'=>'Agrupar na regional.','regional'=>'Amostra regional insuficiente.'] as $period=>$reason) {
    $r=TeamRecognitionService::calculate(array_replace($case,['due_count'=>299,'excluded_count'=>0,'period'=>$period]));
    verify($r['status']==='blocked' && in_array($reason,$r['blockers'],true),'Insufficient sample routes to '.$period);
}
verify(TeamRecognitionService::calculate(array_replace($case,['due_count'=>0,'excluded_count'=>0,'on_time_count'=>0]))['status']==='not_assessable','Zero denominator is never zero performance');
foreach([['due_count'=>-1],['excluded_count'=>321],['on_time_count'=>309],['baseline_percent'=>101],['frozen_verified'=>1]] as $invalid) denied(fn()=>TeamRecognitionService::calculate(array_replace($case,$invalid)),422);
$engine=new TeamRecognitionService($s,$manager);
verify(count($engine->teams()['teams'])===1,'Manager sees company team only');
$simulation=$engine->simulate($case);
verify(!$simulation['payment_authorized'] && $simulation['mode']==='simulation' && $simulation['source']==='manual_scenario','Passing scenario never authorizes payment or claims observed evidence');
denied(fn()=>$engine->simulate(array_replace($case,['team_id'=>2])),404);
foreach([$doctor,$nurse,$navigator] as $actor) {
    $member=new TeamRecognitionService($s,$actor);
    verify(count($member->teams()['teams'])===1,'Reference team member sees own team');
    denied(fn()=>$member->simulate($case),403);
}
foreach([$worker,$dependent] as $actor) denied(fn()=>(new TeamRecognitionService($s,$actor))->teams(),403);
$old=new DoctorReactivationService(new CareService($s,$doctor));
$s->execute('UPDATE companies SET reactivation_enabled=1 WHERE id=1');
$s->insert('doctor_reactivation_awards',['company_id'=>1,'patient_id'=>1,'professional_id'=>1,'baseline_type'=>'appointment','baseline_id'=>99999,'return_type'=>'appointment','return_id'=>99998,'gap_days'=>180,'threshold_days'=>180,'points'=>30,'policy_at'=>date('Y-m-d H:i:s'),'earned_at'=>date('Y-m-d H:i:s')]);
$before=(int)$s->one('SELECT COUNT(*) n FROM doctor_reactivation_awards')['n'];
$versionBefore=(int)$s->one('SELECT gamification_version FROM companies WHERE id=1')['gamification_version'];
$s->execute(file_get_contents(dirname(__DIR__).'/database/migrations/011_collective_recognition.sql'));
verify((int)$s->one('SELECT reactivation_enabled FROM companies WHERE id=1')['reactivation_enabled']===0,'Upgrade disables existing individual program');
verify((int)$s->one('SELECT gamification_version FROM companies WHERE id=1')['gamification_version']===$versionBefore+1,'Upgrade invalidates stale configuration versions');
$s->execute('UPDATE companies SET reactivation_enabled=1 WHERE id=1');
verify($old->sync()===['enabled'=>false,'replacement'=>'team_recognition'],'Retired endpoint cannot accrue even with stale enabled flag');
verify((int)$s->one('SELECT COUNT(*) n FROM doctor_reactivation_awards')['n']===$before,'Retirement leaves archive unchanged');
$industry=new IndustryService($s,$manager);
denied(fn()=>$industry->setGamification(['enabled'=>true,'version'=>$industry->gamification()['version'],'reactivation_enabled'=>true]),422);
verify($industry->gamification()['recognition_mode']==='team_simulation','Company reports collective replacement');
echo "OK: {$count} combined collective recognition/domain checks\n";
