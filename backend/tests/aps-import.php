<?php
ob_start();require __DIR__.'/demo-experience.php';
use App\Services\{ApsImportService,DemoWorkspace};
verify(ApsImportService::date('15/11/2026')==='2026-11-15','Brazilian date import');
verify(ApsImportService::date(46341)==='2026-11-15','Excel date import');
try{ApsImportService::date('30/02/2026');throw new RuntimeException('Invalid date accepted');}catch(InvalidArgumentException){}
$_ENV['APP_ENV']='demo';$_ENV['PUBLIC_DEMO_ACCESS']='true';$_ENV['DB_DATABASE']='viva_elo_demo_abcdef123456';
$service=new ApsImportService($s,$admin);$input=['rows'=>[['T-1','Teste','Empresa','ATIVO','15/11/1988'],['T-1','Repetido','Empresa','ATIVO','15/11/1988'],['T-3','Erro','Empresa','INVÁLIDO','15/11/1988']],'mapping'=>['registration','name','company','status','birth_date'],'generate'=>true,'preview'=>true];
$before=count(DemoWorkspace::people($s,1));$preview=$service->import($input);verify($preview['valid']===1&&count($preview['errors'])===2,'Valid rows and line errors');verify(count(DemoWorkspace::people($s,1))===$before,'Preview never writes people');
$import=$service->import(array_replace($input,['preview'=>false]));verify($import['imported']===1,'Valid lines committed');
verify($service->last()['report']['imported']===1,'Last load report');
$generated=ApsImportService::generate(['registration'=>'X','risk_current'=>4,'plan_status'=>'NÃO ADERIU'],0,'2026-11-15');verify($generated['risk_current']===4&&$generated['plan_status']==='NÃO ADERIU','Generator preserves filled fields');
verify($generated===ApsImportService::generate(['registration'=>'X','risk_current'=>4,'plan_status'=>'NÃO ADERIU'],0,'2026-11-15'),'Generator deterministic');
$old=DemoWorkspace::key();DemoWorkspace::reset();verify(DemoWorkspace::key()!==$old,'Restore uses new isolated scope');
verify(DemoWorkspace::people($s,1)===[],'New visitor does not see prior visitor import');
ob_end_flush();echo "OK: {$count} combined APS checks\n";
