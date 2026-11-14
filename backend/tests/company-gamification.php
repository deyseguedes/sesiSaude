<?php
declare(strict_types=1);
require __DIR__.'/worker-journey.php';
use App\Services\{IndustryService,WorkerJourneyService};
$tenant=$s->insert('companies',['name'=>'Empresa com adoção opcional']);
$tenantManager=$s->insert('users',['company_id'=>$tenant,'name'=>'Gestora fictícia','email'=>'adoption@test.invalid','password_hash'=>'unused','role'=>'manager','management_level'=>'company']);
$policy=new IndustryService($s,$s->one('SELECT * FROM users WHERE id=?',[$tenantManager]));
verify($policy->gamification()['enabled']===false,'New companies start without adopting gamification');
$tenantDoctor=$s->insert('users',['company_id'=>$tenant,'name'=>'Profissional fictício','email'=>'doctor-adoption@test.invalid','password_hash'=>'unused','role'=>'doctor']);
$workers=[];
foreach([1,2] as $index) {
    $uid=$s->insert('users',['company_id'=>$tenant,'name'=>'Trabalhador fictício '.$index,'email'=>'adoption-worker'.$index.'@test.invalid','password_hash'=>'unused','role'=>'worker']);
    $pid=$s->insert('patients',['company_id'=>$tenant,'user_id'=>$uid,'name'=>'Pessoa fictícia '.$index]);
    $appointment=$s->insert('appointments',['company_id'=>$tenant,'patient_id'=>$pid,'professional_id'=>$tenantDoctor,'scheduled_at'=>date('Y-m-d H:i:s',time()+86400),'status'=>'expected']);
    $workers[]=['service'=>new WorkerJourneyService($s,$s->one('SELECT * FROM users WHERE id=?',[$uid])),'patient'=>$pid,'appointment'=>$appointment];
}
foreach($workers as $entry) {
    $disabled=$entry['service']->sync();verify($disabled===['enabled'=>false,'available'=>false],'Disabled policy returns no points or personal award data');
}
verify((int)$s->one('SELECT COUNT(*) n FROM worker_journey_awards WHERE company_id=?',[$tenant])['n']===0,'Disabled companies cannot generate awards');
foreach([$nurse,$doctor,$navigator,$worker,$dependent] as $actor) denied(fn()=>(new IndustryService($s,$actor))->gamification(),403);
denied(fn()=>(new IndustryService($s,$manager,$tenant))->gamification(),404);
denied(fn()=>(new IndustryService($s,$admin,$tenant))->gamification(),404);
denied(fn()=>$policy->setGamification(['enabled'=>'true','version'=>1]),422);
$enabled=$policy->setGamification(['enabled'=>true,'version'=>1]);verify($enabled['enabled'] && $enabled['version']===2,'Company manager may adopt for their company');
denied(fn()=>$policy->setGamification(['enabled'=>false,'version'=>1]),409);
foreach($workers as $entry) verify($entry['service']->sync()['points']===10,'All workers benefit from the same company adoption');
$before=(int)$s->one('SELECT COUNT(*) n FROM worker_journey_awards WHERE company_id=?',[$tenant])['n'];
$off=$policy->setGamification(['enabled'=>false,'version'=>2]);
$s->execute('UPDATE appointments SET status=?,scheduled_at=? WHERE id=?',['attended',date('Y-m-d H:i:s',time()-86400),$workers[0]['appointment']]);
foreach($workers as $entry) verify($entry['service']->sync()===['enabled'=>false,'available'=>false],'Disabling applies to every worker');
verify((int)$s->one('SELECT COUNT(*) n FROM worker_journey_awards WHERE company_id=?',[$tenant])['n']===$before,'Disabling preserves history without new recognition');
$policy->setGamification(['enabled'=>true,'version'=>$off['version']]);
verify($workers[0]['service']->sync()['points']===30 && $workers[1]['service']->sync()['points']===10,'Reactivation resumes independent journeys without duplicate booking points');
$newUid=$s->insert('users',['company_id'=>$tenant,'name'=>'Novo trabalhador fictício','email'=>'new-adoption@test.invalid','password_hash'=>'unused','role'=>'worker']);
$s->insert('patients',['company_id'=>$tenant,'user_id'=>$newUid,'name'=>'Novo cadastro fictício']);
verify((new WorkerJourneyService($s,$s->one('SELECT * FROM users WHERE id=?',[$newUid])))->sync()['enabled'],'New workers inherit active adoption without a per-user setting');
$otherTenant=$s->insert('companies',['name'=>'Outra empresa ainda não aderente']);
verify((int)$s->one('SELECT gamification_enabled FROM companies WHERE id=?',[$otherTenant])['gamification_enabled']===0,'Adopting one company never changes another company');
$s->insert('management_company_access',['user_id'=>$admin['id'],'company_id'=>$tenant]);
$platform=new IndustryService($s,$admin,$tenant);verify($platform->gamification()['company_id']===$tenant,'Platform administrator still requires explicit company authorization');
verify($s->one('SELECT id FROM audit_logs WHERE company_id=? AND event=?',[$tenant,'gamification_disabled'])!==null,'Adoption changes are audited in the selected company');
echo "OK: {$count} combined adoption/domain checks\n";
