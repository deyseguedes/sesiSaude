<?php
declare(strict_types=1);
callApi('GET','/nursing/worklist',401);
foreach(['admin','manager','worker','dependent','navigator'] as $role) {
    login($role);callApi('GET','/nursing/worklist',403);callApi('POST','/nursing/worklist/sync',403);callApi('POST','/nursing/worklist/tasks',403,['title'=>'Fora do perfil']);
}
$nurse=login('nurse');callApi('POST','/nursing/worklist/sync',403,[],false);
callApi('POST','/nursing/worklist/sync',200);
$queue=callApi('GET','/nursing/worklist',200);
if(!$queue['items']) throw new RuntimeException('Nursing queue missing existing records');
$item=$queue['items'][0];$detail=callApi('GET','/nursing/worklist/'.$item['id'],200);
if($detail['summary']['patient']['id']!==(int)$item['patient_id']) throw new RuntimeException('Wrong worklist patient sheet');
callApi('GET','/nursing/worklist?status=invalid',422);
callApi('POST','/nursing/worklist/'.$item['id'].'/actions',403,['version'=>(int)$detail['version'],'type'=>'start'],false);
$result=callApi('POST','/nursing/worklist/'.$item['id'].'/actions',201,['version'=>(int)$detail['version'],'type'=>'contact','channel'=>'phone','outcome'=>'no_answer','note'=>'Tentativa fictícia de contato.']);
callApi('POST','/nursing/worklist/'.$item['id'].'/actions',409,['version'=>(int)$detail['version'],'type'=>'start']);
callApi('POST','/nursing/worklist/'.$item['id'].'/actions',422,['version'=>$result['version'],'type'=>'resolve','note'=>'']);
$result=callApi('POST','/nursing/worklist/'.$item['id'].'/actions',201,['version'=>$result['version'],'type'=>'resolve','note'=>'Acompanhamento pactuado com a equipe.']);
$closed=callApi('GET','/nursing/worklist/'.$item['id'],200);
if($closed['status']!=='resolved' || $closed['history'][0]['note']!=='Acompanhamento pactuado com a equipe.') throw new RuntimeException('Resolution not persisted');
callApi('POST','/nursing/worklist/'.$item['id'].'/actions',201,['version'=>$result['version'],'type'=>'reopen','note'=>'Novo acompanhamento necessário.']);
$task=callApi('POST','/nursing/worklist/tasks',201,['patient_id'=>(int)$item['patient_id'],'team_id'=>1,'assigned_to'=>(int)$nurse['id'],'kind'=>'reschedule_request','title'=>'Verificar novo horário fictício','due_date'=>date('Y-m-d'),'note'=>'Pedido administrativo fictício.']);
callApi('GET','/nursing/worklist/'.$task['id'],200);
callApi('POST','/nursing/worklist/tasks',404,['patient_id'=>$unlinked,'team_id'=>1,'assigned_to'=>(int)$nurse['id'],'kind'=>'manual_task','title'=>'Fora do vínculo','due_date'=>date('Y-m-d')]);
$s->execute('UPDATE care_team_assignments SET active=0 WHERE patient_id=?',[$item['patient_id']]);
callApi('GET','/nursing/worklist/'.$item['id'],404);
$s->execute('UPDATE care_team_assignments SET active=1 WHERE patient_id=?',[$item['patient_id']]);
login('manager');callApi('GET','/nursing/worklist/'.$item['id'],403);
callApi('POST','/auth/logout',200);
