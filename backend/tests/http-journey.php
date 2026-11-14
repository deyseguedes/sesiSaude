<?php
declare(strict_types=1);
callApi('GET','/me/journey',401);
foreach(['admin','manager','doctor','nurse','navigator','dependent'] as $role) {
    login($role);callApi('GET','/me/journey',403);callApi('POST','/me/journey/sync',403);
}
login('manager');$policy=callApi('GET','/management/gamification',200);
callApi('PUT','/management/gamification',200,['enabled'=>true,'version'=>$policy['version']]);
login('worker');callApi('POST','/me/journey/sync',403,null,false);
$journey=callApi('POST','/me/journey/sync',200);
if($journey['points']<10 || $journey['points']>60 || count($journey['missions'])!==3) throw new RuntimeException('Invalid worker journey');
$same=callApi('POST','/me/journey/sync',200,['patient_id'=>$otherPatient,'points'=>999999,'milestone'=>'followup']);
if($same['points']!==$journey['points']) throw new RuntimeException('Client could tamper with points');
$read=callApi('GET','/me/journey?patient_id='.$otherPatient,200);
if($read['points']!==$journey['points']) throw new RuntimeException('Query could select another person');
callApi('POST','/auth/logout',200);
