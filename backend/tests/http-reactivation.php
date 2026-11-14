<?php
declare(strict_types=1);
// Reset login counters only inside this isolated test database.
$s->execute('DELETE FROM login_attempts');
callApi('GET','/professional/reactivation',401);
foreach(['admin','manager','nurse','navigator','worker','dependent'] as $role) {
    login($role);
    callApi('GET','/professional/reactivation',403);
    callApi('POST','/professional/reactivation/sync',403);
}
login('manager');$policy=callApi('GET','/management/gamification',200);
callApi('PUT','/management/gamification',422,['enabled'=>true,'version'=>$policy['version'],'reactivation_enabled'=>'true']);
callApi('PUT','/management/gamification',422,['enabled'=>true,'version'=>$policy['version'],'reactivation_days'=>0]);
callApi('PUT','/management/gamification',422,['enabled'=>true,'version'=>$policy['version'],'reactivation_points'=>1.5]);
$policy=callApi('PUT','/management/gamification',200,['enabled'=>true,'version'=>$policy['version'],'reactivation_enabled'=>true,'reactivation_days'=>180,'reactivation_points'=>30]);
$doctor=login('doctor');
callApi('POST','/professional/reactivation/sync',403,null,false);
$result=callApi('POST','/professional/reactivation/sync',200,['points'=>99999,'reactivation_days'=>0,'professional_id'=>2]);
if($result['threshold_days']!==180 || $result['bonus_points']!==30 || $result['points']!==0) throw new RuntimeException('Client forged physician recognition');
callApi('GET','/professional/reactivation',200);
login('manager');$policy=callApi('PUT','/management/gamification',200,['enabled'=>true,'version'=>$policy['version'],'reactivation_enabled'=>false]);
login('doctor');
if(callApi('POST','/professional/reactivation/sync',200)!==['enabled'=>false]) throw new RuntimeException('Disabled bonus was exposed');
callApi('POST','/auth/logout',200);
