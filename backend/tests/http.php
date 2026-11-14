<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use App\Repositories\Store;

date_default_timezone_set('America/Bahia');
$host=getenv('MYSQL_TEST_HOST')?:'127.0.0.1'; $port=getenv('MYSQL_TEST_PORT')?:'3307';
$dbUser=getenv('MYSQL_TEST_USER')?:'root'; $dbPass=getenv('MYSQL_TEST_PASSWORD')?:'';
$admin=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='viva_elo_test_'.bin2hex(random_bytes(6));
if(!preg_match('/^viva_elo_test_[a-f0-9]{12}$/D',$name)) throw new RuntimeException('Unsafe test database');
$admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$password=bin2hex(random_bytes(16)); $backend=dirname(__DIR__); $server=null; $checks=0;
$env=array_merge(getenv(),['APP_ENV'=>'local','APP_KEY'=>base64_encode(random_bytes(32)),'DB_HOST'=>$host,'DB_PORT'=>$port,'DB_DATABASE'=>$name,'DB_USERNAME'=>$dbUser,'DB_PASSWORD'=>$dbPass,'DEMO_PASSWORD'=>$password]);
function cli(array $args): void {
    global $env,$backend;
    $p=proc_open([PHP_BINARY,...$args],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,$backend,$env);
    if(proc_close($p)!==0) throw new RuntimeException('CLI fixture failed');
}
$cookie=''; $csrf=''; $url='';
function callApi(string $method,string $path,int $expected,?array $body=null,bool $withCsrf=true): mixed {
    global $cookie,$csrf,$url,$checks;
    $curl=curl_init($url.'/api/v1'.$path);
    $headers=['Accept: application/json','Content-Type: application/json'];
    if($withCsrf && $csrf) $headers[]='X-CSRF-Token: '.$csrf;
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_COOKIE=>$cookie,CURLOPT_HEADERFUNCTION=>function($curl,$line)use(&$cookie){if(preg_match('/^Set-Cookie:\s*(viva_elo=[^;]+)/i',$line,$m)) $cookie=$m[1];return strlen($line);}]);
    if($body!==null) curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
    $raw=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    if($status!==$expected) throw new RuntimeException("$method $path expected $expected, got $status: $raw");
    $payload=json_decode($raw,true,32,JSON_THROW_ON_ERROR); $checks++;
    if($expected<400 && $payload['error']!==null) throw new RuntimeException('Unexpected error contract');
    return $payload['data'];
}
function login(string $role): array {
    global $cookie,$csrf,$password;
    $cookie=''; $csrf=''; $session=callApi('GET','/auth/session',200); $csrf=$session['csrf_token'];
    $session=callApi('POST','/auth/login',200,['email'=>$role.'@demo.vivaelo.test','password'=>$password]);
    $csrf=$session['csrf_token']; return $session['user'];
}
try {
    cli(['bin/migrate.php']); cli(['bin/migrate.php']); cli(['bin/seed.php']); cli(['bin/sync-continuity.php']);
    $pdo=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $s=new Store($pdo);
    $other=$s->insert('companies',['name'=>'Outra empresa fictícia']);
    $otherPatient=$s->insert('patients',['company_id'=>$other,'name'=>'Outro paciente']);
    $unlinked=$s->insert('patients',['company_id'=>1,'name'=>'Sem vínculo']);
    $listen=random_int(18000,22000); $url='http://127.0.0.1:'.$listen;
    $server=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$listen,'-t','public','public/router.php'],[0=>['pipe','r'],1=>['file',$backend.'/storage/logs/http-test.log','a'],2=>['file',$backend.'/storage/logs/http-test.log','a']],$pipes,$backend,$env);
    for($attempt=0;$attempt<30;$attempt++) { $socket=@fsockopen('127.0.0.1',$listen,$errno,$error,0.1); if($socket){fclose($socket);break;} usleep(100000); }
    callApi('GET','/health',200);
    callApi('GET','/clinical/patients',401);
    $session=callApi('GET','/auth/session',200); $csrf=$session['csrf_token'];
    callApi('POST','/auth/login',403,['email'=>'doctor@demo.vivaelo.test','password'=>$password],false);
    callApi('POST','/auth/login',401,['email'=>'doctor@demo.vivaelo.test','password'=>'wrong']);
    $doctor=login('doctor');
    $patients=callApi('GET','/clinical/patients',200);
    if(count($patients)!==3) throw new RuntimeException('Patient filtering failed');
    callApi('GET','/clinical/patients/'.$otherPatient,404); callApi('GET','/clinical/patients/'.$unlinked,404);
    callApi('GET','/clinical/patients/1',200);
    $data=['patient_id'=>1,'professional_id'=>(int)$doctor['id'],'team_id'=>1,'start_date'=>date('Y-m-d'),'reassessment_date'=>date('Y-m-d',time()+86400),'objectives'=>'Objetivo fictício','interventions'=>'Ação pactuada','clinical_notes'=>'Observação restrita','status'=>'active'];
    callApi('POST','/care-plans',403,$data,false);
    callApi('POST','/care-plans',422,array_replace($data,['reassessment_date'=>'2026-02-30']));
    $plan=callApi('POST','/care-plans',201,$data)['id'];
    callApi('GET','/care-plans',200); callApi('GET','/care-plans/'.$plan,200);
    $indicator=callApi('POST','/indicators',201,['patient_id'=>1,'name'=>'Indicador fictício','type'=>'numeric','unit'=>'unidade','measurement_method'=>'Demonstração','frequency'=>'Pactuada'])['id'];
    callApi('GET','/clinical/patients/1/indicators',200);
    $goalData=['indicator_id'=>$indicator,'description'=>'Meta pactuada fictícia','initial_value'=>0,'target_value'=>1,'direction'=>'increase','measurement_source'=>'professional','frequency'=>'Pactuada','deadline'=>date('Y-m-d',time()+86400),'responsible_id'=>(int)$doctor['id'],'status'=>'active','min_samples'=>1];
    $goal=callApi('POST','/care-plans/'.$plan.'/goals',201,$goalData)['id'];
    if(callApi('GET','/care-plans/'.$plan.'/goals',200)[0]['progress']['state']!=='insufficient_data') throw new RuntimeException('Missing evidence falsely attained');
    $measurement=['value'=>2,'source'=>'self_reported','collected_at'=>date('Y-m-d H:i:s'),'method'=>'Demonstração'];
    callApi('POST','/indicators/'.$indicator.'/measurements',201,$measurement);
    if(callApi('GET','/care-plans/'.$plan.'/goals',200)[0]['progress']['state']!=='insufficient_data') throw new RuntimeException('Mixed measurement origins');
    callApi('POST','/indicators/'.$indicator.'/measurements',201,array_replace($measurement,['source'=>'professional']));
    callApi('POST','/indicators/'.$indicator.'/measurements',409,array_replace($measurement,['source'=>'professional']));
    if(callApi('GET','/care-plans/'.$plan.'/goals',200)[0]['progress']['state']!=='attained') throw new RuntimeException('Valid measured target not attained');
    callApi('GET','/indicators/'.$indicator.'/measurements',200);
    callApi('PUT','/care-goals/'.$goal,200,array_replace($goalData,['version'=>1,'target_value'=>3]));
    callApi('PUT','/care-plans/'.$plan,200,$data+['version'=>1]);
    callApi('PUT','/care-plans/'.$plan,409,$data+['version'=>1]);
    callApi('POST','/care-plans/'.$plan.'/reviews',201,['version'=>2,'notes'=>'Revisão registrada','outcome'=>'active','reassessment_date'=>date('Y-m-d',time()+172800)]);
    callApi('GET','/clinical/dashboard',200); callApi('GET','/nursing/dashboard',403);
    $nurse=login('nurse');
    callApi('GET','/nursing/dashboard',200); callApi('GET','/clinical/dashboard',403);
    callApi('POST','/care-plans/'.$plan.'/evolutions',201,['kind'=>'education','notes'=>'Ação educativa fictícia']);
    callApi('POST','/care-plans/'.$plan.'/evolutions',422,['kind'=>'clinical','notes'=>'Fora do tipo de registro']);
    callApi('POST','/care-plans/'.$plan.'/adherence',201,['action_description'=>'Ação fictícia','completed'=>true,'source'=>'self_reported','occurred_at'=>date('Y-m-d H:i:s')]);
    callApi('POST','/clinical/referrals',201,['patient_id'=>1,'team_id'=>1,'to_professional_id'=>(int)$doctor['id'],'reason'=>'Motivo clínico restrito']);
    callApi('POST','/clinical/patients/1/appointments',201,['team_id'=>1,'professional_id'=>(int)$nurse['id'],'date'=>date('Y-m-d',time()+86400),'time'=>'11:00']);
    login('navigator'); callApi('GET','/clinical/patients',403); callApi('GET','/care-plans/'.$plan,403); callApi('GET','/indicators/'.$indicator.'/measurements',403);
    $cases=callApi('GET','/continuity/cases',200); $case=$cases[0];
    callApi('POST','/continuity/cases/'.$case['id'].'/actions',201,['type'=>'barrier','barrier'=>'transport','version'=>1]);
    callApi('POST','/continuity/cases/'.$case['id'].'/actions',409,['type'=>'confirm','version'=>1]);
    callApi('POST','/continuity/cases/'.$case['id'].'/actions',201,['type'=>'clinical_review','version'=>2]);
    callApi('GET','/continuity/cases/'.$case['id'],200); callApi('GET','/continuity/dashboard',200);
    callApi('POST','/continuity/sync',200);
    foreach(['admin','manager','worker'] as $role) { login($role); callApi('GET','/portal',200); callApi('GET','/clinical/patients',403); callApi('GET','/continuity/cases',403); }
    callApi('POST','/auth/logout',200); callApi('GET','/portal',401);
    require __DIR__.'/http-industry.php';
    $session=callApi('GET','/auth/session',200); $csrf=$session['csrf_token'];
    for($attempt=0;$attempt<10;$attempt++) callApi('POST','/auth/login',401,['email'=>'rate-limit@demo.vivaelo.test','password'=>'wrong']);
    callApi('POST','/auth/login',429,['email'=>'rate-limit@demo.vivaelo.test','password'=>$password]);
    echo "OK: {$checks} HTTP checks on MySQL\n";
} finally {
    if(is_resource($server)) { proc_terminate($server); proc_close($server); }
    $admin->exec("DROP DATABASE `$name`");
}
