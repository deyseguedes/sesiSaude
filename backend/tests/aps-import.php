<?php
ob_start();require __DIR__.'/demo-experience.php';
use App\Services\{ApsImportService,DemoWorkspace};
verify(ApsImportService::date('15/11/2026')==='2026-11-15','Brazilian date import');
verify(ApsImportService::date(1)==='1900-01-01','Early Excel date excludes fictional leap day');
verify(ApsImportService::date(46341)==='2026-11-15','Excel date import');
try{ApsImportService::date('30/02/2026');throw new RuntimeException('Invalid date accepted');}catch(InvalidArgumentException){}
$_ENV['APP_ENV']='demo';$_ENV['PUBLIC_DEMO_ACCESS']='true';$_ENV['DB_DATABASE']='viva_elo_demo_abcdef123456';
$service=new ApsImportService($s,$admin);$input=['rows'=>[['T-1','Teste','Empresa','ATIVO','15/11/1988'],['T-1','Repetido','Empresa','ATIVO','15/11/1988'],['T-3','Erro','Empresa','INVÁLIDO','15/11/1988']],'mapping'=>['registration','name','company','status','birth_date'],'generate'=>true,'preview'=>true];
$before=count(DemoWorkspace::people($s,1));$invalidRisk=$input;$invalidRisk['mapping'][]='risk_current';$invalidRisk['rows']=[['X','Teste','Empresa','ATIVO','15/11/1988','0']];verify(count($service->import($invalidRisk)['errors'])===1,'Risk zero is rejected');$preview=$service->import($input);verify($preview['valid']===1&&count($preview['errors'])===2,'Valid rows and line errors');verify(count(DemoWorkspace::people($s,1))===$before,'Preview never writes people');
$import=$service->import(array_replace($input,['preview'=>false]));verify($import['imported']===1,'Valid lines committed');
verify($service->last()['report']['imported']===1,'Last load report');
$generated=ApsImportService::generate(['registration'=>'X','risk_current'=>4,'plan_status'=>'NÃO ADERIU'],0,'2026-11-15');verify($generated['risk_current']===4&&$generated['plan_status']==='NÃO ADERIU','Generator preserves filled fields');
verify($generated===ApsImportService::generate(['registration'=>'X','risk_current'=>4,'plan_status'=>'NÃO ADERIU'],0,'2026-11-15'),'Generator deterministic');
$old=DemoWorkspace::key();DemoWorkspace::reset();verify(DemoWorkspace::key()!==$old,'Restore uses new isolated scope');
verify(DemoWorkspace::people($s,1)===[],'New visitor does not see prior visitor import');

$config=require dirname(__DIR__).'/config/outreach.php';
$base=['registration'=>'DEMO-0001','status'=>'ATIVO','state'=>'pending','return_date'=>'2026-11-25','message_sent_at'=>'2026-11-10 10:00:00','risk_current'=>3,'reply'=>null];
$rule=App\Services\OutreachRules::classify($base,'2026-11-15',$config);
verify($rule['category']==='unanswered'&&$rule['why']==='retorno vence em 10 dias; mensagem sem resposta há 5 dias','Explainable baseline rule');
verify(App\Services\OutreachRules::classify(array_replace($base,['state'=>'confirmed']),'2026-11-15',$config)===null,'Confirmed leaves call queue');
verify(App\Services\OutreachRules::classify(array_replace($base,['message_sent_at'=>'2026-11-14']),'2026-11-15',$config)['category']==='waiting','Recent message stays in separate section');
verify(App\Services\OutreachRules::compare(['registration'=>'A','days_due'=>-1,'reply'=>null,'unanswered_count'=>1,'risk_current'=>1],['registration'=>'B','days_due'=>2,'reply'=>'cannot','unanswered_count'=>5,'risk_current'=>4])<0,'Overdue wins before risk');

ob_end_flush();echo "OK: {$count} combined APS checks\n";
