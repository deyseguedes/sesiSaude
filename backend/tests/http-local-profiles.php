<?php
declare(strict_types=1);
// Restart only this isolated test server, using the same disposable database.
proc_terminate($server); proc_close($server);
$env['LOCAL_PROFILE_ACCESS']='true';
$listen=random_int(23000,26000); $url='http://127.0.0.1:'.$listen;
$server=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$listen,'-t','public','public/router.php'],[0=>['pipe','r'],1=>['file',$backend.'/storage/logs/http-test.log','a'],2=>['file',$backend.'/storage/logs/http-test.log','a']],$pipes,$backend,$env);
for($attempt=0;$attempt<30;$attempt++) { $socket=@fsockopen('127.0.0.1',$listen,$errno,$error,0.1); if($socket){fclose($socket);break;} usleep(100000); }
$cookie=''; $csrf='';
$session=callApi('GET','/auth/session',200); $csrf=$session['csrf_token'];
if(count($session['local_profiles'])!==7) throw new RuntimeException('Local profiles missing');
callApi('POST','/auth/local-profile',403,['role'=>'doctor'],false);
callApi('POST','/auth/local-profile',422,['role'=>'invalid']);
foreach(['admin','manager','doctor','nurse','navigator','worker','dependent'] as $role) {
    $previous=$csrf;
    $session=callApi('POST','/auth/local-profile',200,['role'=>$role]); $csrf=$session['csrf_token'];
    if($session['user']['role']!==$role || $csrf===$previous) throw new RuntimeException('Invalid local identity or CSRF rotation');
    callApi('GET','/clinical/patients',in_array($role,['doctor','nurse'],true)?200:403);
    $current=callApi('GET','/auth/session',200);
    if($current['user']['id']!==$session['user']['id']) throw new RuntimeException('Local session not persisted');
}
$s->execute('UPDATE users SET active=0 WHERE email=?',['nurse@demo.vivaelo.test']);
callApi('POST','/auth/local-profile',404,['role'=>'nurse']);
$s->execute('UPDATE users SET active=1 WHERE email=?',['nurse@demo.vivaelo.test']);
callApi('POST','/auth/logout',200);
callApi('GET','/clinical/patients',401);
if((int)$s->one('SELECT COUNT(*) n FROM audit_logs WHERE event=?',['local_profile_login'])['n']!==7) throw new RuntimeException('Local login audit missing');
