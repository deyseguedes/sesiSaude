<?php
declare(strict_types=1);
require dirname(__DIR__).'/environment.php';
use App\Services\{LocalProfileAccess,Crypto,CareService,ClinicalReturnService};
use App\Repositories\Store;
if(PHP_SAPI!=='cli'||!LocalProfileAccess::publicDemo())throw new RuntimeException('Somente banco demonstrativo.');
$s=new Store(App\Core\Database::connect());$worker=$s->one('SELECT * FROM users WHERE email=?',['worker@demo.vivaelo.test']);$c=(int)$worker['company_id'];
$s->transaction(function()use($s,$worker,$c){
 $rows=$s->all('SELECT * FROM aps_people WHERE company_id=? AND visit_key=?',[$c,'template']);$marcos=null;
 foreach($rows as $i=>$r){$d=json_decode(Crypto::decrypt($r['payload']),true);if(!isset($d['semester_attended'])){$d['semester_attended']=$i%3!==0;$d['semester_return_on_time']=$i%4!==0;$d['semester_examples']=true;if($r['registration']==='DEMO-0001'){$d['semester_attended']=true;$d['semester_return_on_time']=true;$d['checkins_answered']=2;}$s->execute('UPDATE aps_people SET payload=? WHERE id=?',[Crypto::encrypt(json_encode($d,JSON_THROW_ON_ERROR)),$r['id']]);}if($r['registration']==='DEMO-0001')$marcos=$r+['data'=>$d];}
 if(!$marcos)return;$patient=(int)$marcos['patient_id'];$old=$s->one('SELECT id FROM patients WHERE user_id=?',[$worker['id']]);
 if(!$old||(int)$old['id']!==$patient){
  if($old){$s->execute('UPDATE patients SET user_id=NULL WHERE id=?',[$old['id']]);$s->execute('UPDATE beneficiary_links SET sponsor_patient_id=? WHERE company_id=? AND sponsor_patient_id=?',[$patient,$c,$old['id']]);}
  $s->execute('UPDATE patients SET user_id=? WHERE id=?',[$worker['id'],$patient]);
  $doctor=$s->one('SELECT * FROM users WHERE email=?',['doctor@demo.vivaelo.test']);$nurse=$s->one('SELECT id FROM users WHERE email=?',['nurse@demo.vivaelo.test']);
  $s->insert('appointments',['company_id'=>$c,'patient_id'=>$patient,'professional_id'=>$doctor['id'],'service_unit_id'=>$marcos['data']['service_unit_id'],'scheduled_at'=>date('Y-m-d',strtotime('-4 days')).' 10:00:00','status'=>'attended']);
  $plan=$s->one('SELECT id FROM care_plans WHERE patient_id=? ORDER BY id LIMIT 1',[$patient]);if($plan){$s->execute('UPDATE care_plans SET created_at=? WHERE id=?',[date('Y-m-d H:i:s',strtotime('-7 days')),$plan['id']]);$s->insert('clinical_evolutions',['plan_id'=>$plan['id'],'author_id'=>$nurse['id'],'kind'=>'nursing','notes'=>Crypto::encrypt('Acompanhamento sintético do semestre anterior; pactuada continuidade compatível com o turno.'),'created_at'=>date('Y-m-d H:i:s',strtotime('-2 days'))]);}
  $risk=$s->one('SELECT version FROM clinical_return_plans WHERE patient_id=?',[$patient]);(new ClinicalReturnService(new CareService($s,$doctor)))->save($patient,['risk'=>3,'assessment_date'=>date('Y-m-d',strtotime('-30 days')),'due_date'=>$marcos['data']['return_date'],'team_id'=>(int)$marcos['data']['team_id'],'version'=>(int)($risk['version']??0),'reason'=>'Ajuste explícito do cenário fictício de Marcos.']);
  foreach($s->all("SELECT id FROM campaigns WHERE company_id=? AND status='active'",[$c]) as $campaign)if(!$s->one('SELECT id FROM campaign_participants WHERE campaign_id=? AND patient_id=?',[$campaign['id'],$patient]))$s->insert('campaign_participants',['campaign_id'=>$campaign['id'],'patient_id'=>$patient,'stage'=>'invited','updated_at'=>date('Y-m-d H:i:s')]);
 }
});
echo "Jornada e semestre fictícios de Marcos preparados.\n";
