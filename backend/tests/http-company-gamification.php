<?php
declare(strict_types=1);
callApi('GET','/management/gamification',401);
foreach(['doctor','nurse','navigator','worker','dependent'] as $role) {
    login($role);callApi('GET','/management/gamification',403);callApi('PUT','/management/gamification',403,['enabled'=>true,'version'=>1]);
}
login('manager');$policy=callApi('GET','/management/gamification',200);
callApi('PUT','/management/gamification',403,['enabled'=>false,'version'=>$policy['version']],false);
callApi('PUT','/management/gamification',422,['enabled'=>'false','version'=>$policy['version']]);
$unauthorizedCompany=$s->insert('companies',['name'=>'Empresa não autorizada para configuração']);
callApi('GET','/management/gamification?company_id='.$unauthorizedCompany,404);
$before=(int)$s->one('SELECT COUNT(*) n FROM worker_journey_awards WHERE company_id=1')['n'];
$off=callApi('PUT','/management/gamification',200,['enabled'=>false,'version'=>$policy['version']]);
login('worker');$hidden=callApi('POST','/me/journey/sync',200,['enabled'=>true]);
if($hidden!==['enabled'=>false,'available'=>false]) throw new RuntimeException('Worker bypassed company adoption');
if((int)$s->one('SELECT COUNT(*) n FROM worker_journey_awards WHERE company_id=1')['n']!==$before) throw new RuntimeException('Disabling did not preserve award history');
login('manager');
callApi('PUT','/management/gamification',409,['enabled'=>true,'version'=>$policy['version']]);
callApi('PUT','/management/gamification',200,['enabled'=>true,'version'=>$off['version']]);
login('worker');$resumed=callApi('POST','/me/journey/sync',200);
if(!$resumed['enabled'] || $resumed['points']<10 || (int)$s->one('SELECT COUNT(*) n FROM worker_journey_awards WHERE company_id=1')['n']!==$before) throw new RuntimeException('Reactivation duplicated or lost recognition');
$platformUser=login('admin');callApi('GET','/management/gamification?company_id='.$unauthorizedCompany,404);
$s->insert('management_company_access',['user_id'=>$platformUser['id'],'company_id'=>$unauthorizedCompany]);
$newPolicy=callApi('GET','/management/gamification?company_id='.$unauthorizedCompany,200);
if($newPolicy['enabled']!==false) throw new RuntimeException('New companies must choose adoption');
callApi('PUT','/management/gamification?company_id='.$unauthorizedCompany,200,['enabled'=>true,'version'=>$newPolicy['version']]);
callApi('POST','/auth/logout',200);
