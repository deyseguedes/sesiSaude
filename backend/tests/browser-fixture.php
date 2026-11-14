<?php
declare(strict_types=1);
// Only used by the local browser QA runner. Credentials travel over stdin/stdout pipes.
require dirname(__DIR__).'/environment.php';
$request=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
if(($_ENV['APP_ENV']??'local')!=='local') throw new RuntimeException('Browser QA requires local environment');
$host=$_ENV['DB_HOST'];$port=$_ENV['DB_PORT'];
$admin=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$_ENV['DB_USERNAME'],$_ENV['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(($request['action']??'')==='drop') {
    $name=$request['database']??'';
    if(!preg_match('/^viva_elo_test_[a-f0-9]{12}$/D',$name)) throw new RuntimeException('Invalid QA database');
    $admin->exec("DROP DATABASE `$name`");echo json_encode(['dropped'=>true]);exit;
}
$name='viva_elo_test_'.bin2hex(random_bytes(6));$password=bin2hex(random_bytes(16));
$admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$overrides=array_merge(array_intersect_key($_ENV,array_flip(['DB_HOST','DB_PORT','DB_USERNAME','DB_PASSWORD'])),['APP_ENV'=>'local','APP_KEY'=>base64_encode(random_bytes(32)),'DB_DATABASE'=>$name,'DEMO_PASSWORD'=>$password]);
$env=array_merge(getenv(),$overrides);
$overrides['LOCAL_PROFILE_ACCESS']=($request['local_profile_access']??false)===true?'true':'false';
$env['LOCAL_PROFILE_ACCESS']=$overrides['LOCAL_PROFILE_ACCESS'];
try {
    foreach(['bin/migrate.php','bin/seed.php'] as $command) {
        $process=proc_open([PHP_BINARY,$command],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$env);
        fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0) throw new RuntimeException('QA fixture initialization failed');
    }
    $_ENV=array_replace($_ENV,$overrides);
    $store=new App\Repositories\Store(App\Core\Database::connect());
    $user=$store->one('SELECT * FROM users WHERE email=?',['admin@demo.vivaelo.test']);
    $industry=new App\Services\IndustryService($store,$user);
    $contract=$store->one('SELECT id FROM service_contracts LIMIT 1');$unit=$store->one('SELECT id FROM industry_units LIMIT 1');
    $enrollment=new App\Services\EnrollmentService($store);
    $invite=$enrollment->invite($industry,['contract_id'=>(int)$contract['id'],'industry_unit_id'=>(int)$unit['id'],'kind'=>'worker','email'=>'pending@demo.vivaelo.test']);
    $enrollment->register(['invitation_token'=>$invite['invitation_token'],'name'=>'Elisa Cadastro Fictício','email'=>'pending@demo.vivaelo.test','password'=>$password,'birth_date'=>'2000-01-01','contact'=>'Contato fictício','postal_code'=>'40000-000','preferred_period'=>'flexible','accept_notice'=>true]);
    $doctor=$store->one('SELECT * FROM users WHERE email=?',['doctor@demo.vivaelo.test']);
    $care=new App\Services\CareService($store,$doctor);
    $plan=$store->one('SELECT id,patient_id FROM care_plans ORDER BY id LIMIT 1');
    $care->evolution((int)$plan['id'],['kind'=>'clinical','notes'=>'Registro fictício para conferir o resumo e sua autoria. '.str_repeat('Texto demonstrativo sem dados reais. ',12).'Fim do registro fictício.']);
    $indicators=new App\Services\IndicatorService($care);
    $indicator=$indicators->create(['patient_id'=>(int)$plan['patient_id'],'name'=>'Indicador fictício de teste','type'=>'numeric','unit'=>'unidades','measurement_method'=>'Registro demonstrativo','frequency'=>'Pactuada'])['id'];
    $indicators->measure($indicator,['value'=>0,'source'=>'professional','collected_at'=>date('Y-m-d H:i:s'),'method'=>'Coleta profissional fictícia']);
    $indicators->measure($indicator,['value'=>1,'source'=>'self_reported','collected_at'=>date('Y-m-d H:i:s'),'method'=>'Autorrelato fictício recebido']);
    if(($request['nursing_scenario']??false)===true) { ob_start();require dirname(__DIR__).'/bin/seed-nursing.php';ob_end_clean(); }
    $liveAppointment=$store->insert('appointments',['company_id'=>$doctor['company_id'],'patient_id'=>$plan['patient_id'],'professional_id'=>$doctor['id'],'scheduled_at'=>date('Y-m-d H:i:s'),'status'=>'confirmed','modality'=>'telehealth']);
    (new App\Services\SchedulingService($store,$doctor))->setAccess($liveAppointment,['meeting_url'=>'https://example.invalid/fictitious-room']);
    // Historical, explicitly fictional evidence for the worker's three-step journey.
    $store->insert('appointments',['company_id'=>$doctor['company_id'],'patient_id'=>$plan['patient_id'],'professional_id'=>$doctor['id'],'scheduled_at'=>date('Y-m-d H:i:s',time()-4*86400),'status'=>'attended']);
    $fixtureJourneyNurse=$store->one('SELECT id FROM users WHERE email=?',['nurse@demo.vivaelo.test']);
    $store->insert('clinical_evolutions',['plan_id'=>$plan['id'],'author_id'=>$fixtureJourneyNurse['id'],'kind'=>'nursing','notes'=>App\Services\Crypto::encrypt('Acompanhamento fictício posterior à consulta, exclusivo do cenário demonstrativo.'),'created_at'=>date('Y-m-d H:i:s',time()-2*86400)]);
    echo json_encode(['database'=>$name,'password'=>$password,'env'=>$overrides],JSON_THROW_ON_ERROR);
} catch(Throwable $error) {$admin->exec("DROP DATABASE `$name`");throw new RuntimeException('QA fixture failed',previous:$error);}
