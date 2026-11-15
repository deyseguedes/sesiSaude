<?php
declare(strict_types=1);
$s->execute('DELETE FROM login_attempts');
callApi('GET','/clinical/patients/1/return',401);
$input=['risk'=>3,'due_date'=>date('Y-m-d',time()+86400),'team_id'=>1,'version'=>0];
foreach(['admin','manager','navigator','worker','dependent'] as $role) {
    login($role);callApi('GET','/clinical/patients/1/return',403);callApi('PUT','/clinical/patients/1/return',403,$input);
}
login('nurse');callApi('GET','/clinical/patients/1/return',200);callApi('PUT','/clinical/patients/1/return',403,$input);
login('doctor');
callApi('GET','/clinical/patients/'.$otherPatient.'/return',404);
callApi('PUT','/clinical/patients/1/return',403,$input,false);
callApi('PUT','/clinical/patients/1/return',422,array_replace($input,['risk'=>5]));
$r=callApi('PUT','/clinical/patients/1/return',200,$input);
if($r['risk']!==3 || $r['version']!==1) throw new RuntimeException('Missing confirmed risk');
callApi('PUT','/clinical/patients/1/return',409,$input);
callApi('PUT','/clinical/patients/1/return',422,array_replace($input,['risk'=>2,'version'=>1]));
callApi('PUT','/clinical/patients/1/return',200,array_replace($input,['risk'=>2,'version'=>1,'reason'=>'Reavaliação fictícia com justificativa.']));
login('nurse');callApi('POST','/nursing/worklist/sync',200);$rows=callApi('GET','/nursing/worklist?status=all',200)['items'];
if(!array_filter($rows,fn($r)=>$r['source_type']==='clinical_return' && (int)$r['patient_id']===1)) throw new RuntimeException('Clinical return not in automatic queue');
callApi('GET','/nursing/worklist?contact_state=lost',200);
callApi('GET','/nursing/worklist?contact_state=bad',422);
callApi('POST','/auth/logout',200);
