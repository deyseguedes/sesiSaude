<?php
declare(strict_types=1);
require dirname(__DIR__).'/environment.php';
use App\Services\{LocalProfileAccess,ApsImportService,Crypto};
use App\Repositories\Store;
if(PHP_SAPI!=='cli'||!LocalProfileAccess::publicDemo())throw new RuntimeException('Somente demonstração isolada.');
$s=new Store(App\Core\Database::connect());$doctor=$s->one('SELECT * FROM users WHERE email=?',['doctor@demo.vivaelo.test']);$c=(int)$doctor['company_id'];
if($s->one('SELECT id FROM aps_people WHERE company_id=? AND visit_key=? AND registration=?',[$c,'template','DEMO-0001'])){echo "Cenário de convocação preservado.\n";exit;}
$s->transaction(function()use($s,$c,$doctor){
 $people=$s->all('SELECT * FROM patients WHERE company_id=? ORDER BY id',[$c]);$now=new DateTimeImmutable('today');$unit=$s->one('SELECT * FROM service_units WHERE company_id=? ORDER BY id LIMIT 1',[$c]);$team=$s->one('SELECT id FROM teams WHERE company_id=? ORDER BY id LIMIT 1',[$c]);
 foreach($people as $i=>$p){$marcos=$i===count($people)-1;$registration=$marcos?'DEMO-0001':'SINT-'.str_pad((string)($i+1),4,'0',STR_PAD_LEFT);
 $r=ApsImportService::generate(['registration'=>$registration,'name'=>$p['name'],'company'=>'Empresa fictícia Aurora','status'=>'ATIVO','sex'=>$i%2?'M':'F','birth_date'=>$now->modify('-'.(25+$i%35).' years')->format('Y-m-d'),'shift'=>$i%2?'06h às 14h':'14h às 22h','doctor'=>$doctor['name'],'phone'=>'7190000'.str_pad((string)$i,4,'0',STR_PAD_LEFT),'cpf'=>'','unit'=>$unit['name']],$i,$now->format('Y-m-d'));
 if($i<19){$r['return_date']=$now->modify('+'.(15+$i%15).' days')->format('Y-m-d');$r['last_consultation']=(new DateTimeImmutable($r['return_date']))->modify('-6 months')->format('Y-m-d');}
 if($marcos){$r=array_replace($r,['name'=>'Marcos — demonstração','birth_date'=>$now->modify('-38 years')->format('Y-m-d'),'occupation'=>'Operador de produção','shift'=>'06h às 14h','risk_initial'=>3,'risk_current'=>3,'return_date'=>$now->modify('+10 days')->format('Y-m-d'),'last_consultation'=>$now->modify('-3 months')->modify('+10 days')->format('Y-m-d'),'plan_status'=>'AGUARDANDO RESPOSTA','message_sent_at'=>$now->modify('-5 days')->format('Y-m-d').' 10:00:00','checkins_answered'=>2]);$s->execute('UPDATE patients SET name=? WHERE id=?',[$r['name'],$p['id']]);}
 $a=$s->insert('appointments',['company_id'=>$c,'patient_id'=>$p['id'],'professional_id'=>$doctor['id'],'service_unit_id'=>$unit['id'],'scheduled_at'=>$r['return_date'].' 15:00:00','status'=>'expected']);
 $case=$s->insert('continuity_cases',['company_id'=>$c,'patient_id'=>$p['id'],'appointment_id'=>$a,'status'=>'pending','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
 $r['appointment_id']=$a;$r['case_id']=$case;$r['scheduled_at']=$r['return_date'].' 15:00:00';$r['team_id']=$team['id'];$r['professional_id']=$doctor['id'];$r['service_unit_id']=$unit['id'];$r['campaign_id']=6;$r['variant']='A';
 $s->insert('aps_people',['company_id'=>$c,'patient_id'=>$p['id'],'visit_key'=>'template','registration'=>$registration,'payload'=>Crypto::encrypt(json_encode($r,JSON_THROW_ON_ERROR)),'test_data'=>1,'updated_at'=>date('Y-m-d H:i:s')]);
 }
});
echo "Base de convocação criada, com Marcos e dados de teste.\n";
