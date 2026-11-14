<?php
declare(strict_types=1);
$s->execute('DELETE FROM login_attempts');
callApi('GET','/recognition/teams',401);
$case=['team_id'=>1,'due_count'=>320,'excluded_count'=>12,'on_time_count'=>169,'baseline_percent'=>46,'satisfaction_percent'=>95,'period'=>'quarter','frozen_verified'=>true,'baseline_verified'=>true,'no_upheld_pressure'=>true,'risk_audit_passed'=>true,'consent_compliant'=>true];
foreach(['worker','dependent'] as $role) { login($role);callApi('GET','/recognition/teams',403);callApi('POST','/recognition/teams/simulate',403,$case); }
foreach(['doctor','nurse','navigator'] as $role) {
    login($role);callApi('GET','/recognition/teams',200);
    callApi('GET','/recognition/teams?company_id=2',404);
    callApi('POST','/recognition/teams/simulate',403,$case);
}
login('manager');$policy=callApi('GET','/management/gamification',200);
callApi('PUT','/management/gamification',422,['enabled'=>true,'version'=>$policy['version'],'reactivation_enabled'=>true]);
callApi('POST','/recognition/teams/simulate',403,$case,false);
$r=callApi('POST','/recognition/teams/simulate',200,$case);
if($r['proposed_percent']!==3 || $r['payment_authorized']!==false) throw new RuntimeException('Wrong collective scenario');
callApi('POST','/recognition/teams/simulate',422,array_replace($case,['on_time_count'=>999]));
$foreignTeam=$s->insert('teams',['company_id'=>$unauthorizedCompany,'name'=>'Foreign recognition team']);
callApi('POST','/recognition/teams/simulate',404,array_replace($case,['team_id'=>$foreignTeam]));
login('doctor');
if(callApi('POST','/professional/reactivation/sync',200,['points'=>99999])!==['enabled'=>false,'replacement'=>'team_recognition']) throw new RuntimeException('Retired individual award accrued');
callApi('POST','/auth/logout',200);
