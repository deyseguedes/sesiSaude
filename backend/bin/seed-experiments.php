<?php
declare(strict_types=1);
require dirname(__DIR__).'/environment.php';
use App\Services\{LocalProfileAccess,Crypto,CampaignExperimentService};
use App\Repositories\Store;
if(PHP_SAPI!=='cli'||!LocalProfileAccess::publicDemo())throw new RuntimeException('Somente banco demonstrativo.');
$s=new Store(App\Core\Database::connect());$c=(int)$s->one('SELECT company_id FROM users WHERE email=?',['admin@demo.vivaelo.test'])['company_id'];
if($s->one('SELECT id FROM campaign_experiments WHERE company_id=? AND visit_key=?',[$c,'template'])){echo "Experimentos preservados.\n";exit;}
$today=new DateTimeImmutable('today');$past=$today->modify('-40 days')->format('Y-m-d');$ended=$today->modify('-1 day')->format('Y-m-d');$future=$today->modify('+30 days')->format('Y-m-d');
$cases=[
 ['Retorno já marcado','return_call',['n1'=>430,'n2'=>430,'x1'=>250,'x2'=>202,'sent'=>430,'responded72'=>350,'scheduled'=>310,'attended'=>250,'on_time'=>250,'satisfaction_percent'=>94],['A'=>['sent'=>430,'read'=>400,'responded'=>350,'scheduled'=>310,'attended'=>250],'B'=>['sent'=>430,'read'=>380,'responded'=>280,'scheduled'=>260,'attended'=>202]]],
 ['Lembrete na véspera às 20h','reminder',['n1'=>2500,'n2'=>2500,'x1'=>1175,'x2'=>1150,'sent'=>2500,'responded72'=>1200,'scheduled'=>1190,'attended'=>1175,'on_time'=>1175,'satisfaction_percent'=>94],['A'=>['sent'=>2500,'read'=>1250,'responded'=>1200,'scheduled'=>1190,'attended'=>1175],'B'=>['sent'=>2500,'read'=>2400,'responded'=>2000,'scheduled'=>1900,'attended'=>1150]]],
 ['Plano de cuidado em PDF longo','orientation',['n1'=>300,'n2'=>300,'x1'=>150,'x2'=>140,'sent'=>300,'responded72'=>170,'scheduled'=>160,'attended'=>150,'optouts'=>21,'satisfaction_percent'=>94],['A'=>['invited'=>300,'interested'=>290,'welcomed'=>280,'participating'=>280,'completed'=>150]]],
 ['Bonificação do trabalhador, piloto','incentive',['n1'=>1000,'n2'=>1000,'x1'=>560,'x2'=>500,'sent'=>2000,'responded72'=>800,'scheduled'=>650,'attended'=>560,'on_time'=>560,'redeemed'=>100,'benefit_cost'=>83,'message_cost'=>.05,'satisfaction_percent'=>94],['A'=>['sent'=>1000,'read'=>950,'responded'=>800,'scheduled'=>650,'attended'=>560],'B'=>['sent'=>1000,'read'=>930,'responded'=>750,'scheduled'=>600,'attended'=>500]]],
 ['Mural da equipe e DDS (desplugado)','offline',['n1'=>60,'n2'=>60,'x1'=>35,'x2'=>28,'sent'=>60,'responded72'=>40,'scheduled'=>38,'attended'=>35,'satisfaction_percent'=>94],['A'=>['sent'=>60,'read'=>50,'responded'=>40,'scheduled'=>38,'attended'=>35]]],
 ['Convocação de retorno, novembro/2026','return_call',null,null]
];
$s->transaction(function()use($s,$c,$cases,$past,$ended,$future){
 $frozen=[];foreach($s->all('SELECT * FROM aps_people WHERE company_id=? AND visit_key=?',[$c,'template']) as $r){$p=json_decode(Crypto::decrypt($r['payload']),true);$variant=CampaignExperimentService::variant($p['registration']);$p['variant']=$variant;$s->execute('UPDATE aps_people SET payload=? WHERE id=?',[Crypto::encrypt(json_encode($p,JSON_THROW_ON_ERROR)),$r['id']]);$frozen[]=['person_id'=>(int)$r['id'],'registration'=>$p['registration'],'variant'=>$variant];}
 foreach($cases as $index=>[$name,$kind,$history,$funnels]){
  $d=['objective'=>'Avaliar participação e continuidade com comparação explícita.','cycle'=>'Ciclo 1','unit'=>'Empresa fictícia Aurora','contents'=>'','channel'=>$kind==='offline'?'dds':'whatsapp','text_a'=>'Seu retorno está marcado. Você pode responder com um toque.','text_b'=>'Agende seu retorno com a equipe.','send_time'=>$index===1?'20:00':'14:30','start_date'=>$past,'end_date'=>$history?$ended:$future,'measurement_from'=>$past,'measurement_to'=>$history?$ended:$future,'main_metric'=>$kind==='orientation'?'adherence':'on_time','comparison'=>$index===1?'historical':($index===3?'stepped':'ab'),'criteria'=>['status'=>'ATIVO','risk'=>'','company'=>'','shift'=>'','care_line'=>''],'minimum_gain_pp'=>4,'max_cost'=>126,'message_cost'=>.05,'hour_cost'=>25,'benefit_cost'=>$index===3?83:0,'frozen'=>$history?[]:$frozen,'frozen_at'=>date('Y-m-d H:i:s'),'simulated'=>true];
  if($history){$d['history']=$history+['period_ended'=>true];$d['funnels']=$funnels;$d['segments']=[['name'=>'Turno 06h às 14h','n'=>max(10,(int)($history['n1']/3)),'rate'=>$index===1?30:45],['name'=>'Canal WhatsApp simulado','n'=>$history['n1'],'rate'=>100*$history['x1']/$history['n1']]];}
  $id=$s->insert('campaign_experiments',['company_id'=>$c,'visit_key'=>'template','version'=>1,'name'=>$name,'kind'=>$kind,'status'=>$history?'closed':'active','payload'=>Crypto::encrypt(json_encode($d,JSON_THROW_ON_ERROR)),'created_at'=>date('Y-m-d H:i:s')]);if($id!==$index+1)throw new RuntimeException('IDs dos exemplos não correspondem ao roteiro.');
 }
});
echo "Seis campanhas demonstrativas carregadas.\n";
