<?php
declare(strict_types=1);
require dirname(__DIR__).'/environment.php';
use App\Services\{LocalProfileAccess,CareService,ClinicalReturnService,Crypto,NursingWorklistService};
use App\Repositories\Store;
if(PHP_SAPI!=='cli'||!LocalProfileAccess::publicDemo())throw new RuntimeException('Carga restrita ao banco isolado de demonstração.');
$s=new Store(App\Core\Database::connect());
$doctor=$s->one('SELECT * FROM users WHERE email=?',['doctor@demo.vivaelo.test']);
$nurse=$s->one('SELECT * FROM users WHERE email=?',['nurse@demo.vivaelo.test']);
$c=$doctor['company_id'];$team=$s->one('SELECT id FROM teams WHERE company_id=? ORDER BY id LIMIT 1',[$c]);
$unit=$s->one('SELECT id FROM service_units WHERE company_id=? ORDER BY id LIMIT 1',[$c]);
$industry=$s->one('SELECT id FROM industry_units WHERE company_id=? ORDER BY id LIMIT 1',[$c]);
$contract=$s->one('SELECT id FROM service_contracts WHERE company_id=? ORDER BY id LIMIT 1',[$c]);
if(!$unit||!$industry||!$contract)throw new RuntimeException('Estrutura de demonstração ausente.');
if(!$s->one('SELECT id FROM users WHERE email=?',['leader@demo.vivaelo.test']))$s->insert('users',['company_id'=>$c,'name'=>'Líder de setor — demonstração','email'=>'leader@demo.vivaelo.test','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'role'=>'leader']);
if($s->one('SELECT id FROM patients WHERE name=? AND company_id=?',['Carla Silva [Teste 001]',$c])) {echo "Carga sintética já instalada; respostas preservadas.\n";exit;}
$csv=fopen(dirname(__DIR__).'/database/fixtures/100-pessoas-sinteticas.csv','r');$header=fgetcsv($csv,0,';');$rows=[];
while(($row=fgetcsv($csv,0,';'))!==false)$rows[]=array_combine($header,$row);fclose($csv);
if(count($rows)!==100)throw new RuntimeException('Carga exige 100 pessoas.');
$s->transaction(function()use($s,$doctor,$nurse,$c,$team,$unit,$industry,$contract,$rows){
 $existing=$s->all('SELECT * FROM patients WHERE company_id=? ORDER BY id',[$c]);if(count($existing)>100)throw new RuntimeException('Banco de demonstração excede o cenário.');
 $s->execute('UPDATE teams SET name=? WHERE id=?',['Equipe Norte',$team['id']]);
 $s->execute('UPDATE users SET management_level=? WHERE id=?',['none',$nurse['id']]);
 $care=new CareService($s,$doctor);
 foreach($rows as $index=>$r){
  $p=$existing[$index]??null;
  if($p){$id=(int)$p['id'];$s->execute('UPDATE patients SET name=? WHERE id=?',[$r['nome'],$id]);}
  else $id=$s->insert('patients',['company_id'=>$c,'name'=>$r['nome']]);
  if(!$s->one('SELECT id FROM care_team_assignments WHERE patient_id=? AND team_id=?',[$id,$team['id']]))$s->insert('care_team_assignments',['company_id'=>$c,'patient_id'=>$id,'team_id'=>$team['id']]);
  if(!$s->one('SELECT id FROM beneficiary_links WHERE patient_id=?',[$id]))$s->insert('beneficiary_links',['company_id'=>$c,'patient_id'=>$id,'contract_id'=>$contract['id'],'industry_unit_id'=>$industry['id'],'kind'=>'worker','status'=>'active','created_at'=>date('Y-m-d H:i:s')]);
  $assessment=date('Y-m-d',strtotime('-60 days'));$due=date('Y-m-d',strtotime($r['dias_retorno'].' days'));
  $plan=$s->one('SELECT id FROM care_plans WHERE patient_id=? ORDER BY id LIMIT 1',[$id]);
  if(!$plan)$plan=$care->create(['patient_id'=>$id,'professional_id'=>(int)$doctor['id'],'team_id'=>(int)$team['id'],'start_date'=>$assessment,'reassessment_date'=>$due,'objectives'=>$r['objetivo'],'interventions'=>$r['intervencao'],'status'=>'active','clinical_notes'=>'Exemplo sintético: '.$r['observacao']]);
  else $s->execute('UPDATE care_plans SET objectives=?,interventions=?,clinical_notes=?,reassessment_date=? WHERE id=?',[$r['objetivo'],$r['intervencao'],Crypto::encrypt('Exemplo sintético: '.$r['observacao']),$due,$plan['id']]);
  $old=$s->one('SELECT version FROM clinical_return_plans WHERE patient_id=?',[$id]);
  (new ClinicalReturnService($care))->save($id,['risk'=>(int)$r['risco'],'assessment_date'=>$assessment,'due_date'=>$due,'team_id'=>(int)$team['id'],'version'=>(int)($old['version']??0),'reason'=>'Ajuste do cenário sintético.']);
  $apptDate=date('Y-m-d',strtotime(($index<2?2:(int)$r['dias_retorno']).' days')).' '.($index%2?'14:30:00':'09:00:00');
  $s->insert('appointments',['company_id'=>$c,'patient_id'=>$id,'professional_id'=>$doctor['id'],'service_unit_id'=>$unit['id'],'scheduled_at'=>$apptDate,'status'=>$index%4===0?'confirmed':'expected']);
  if($index%3!==0)$s->insert('appointments',['company_id'=>$c,'patient_id'=>$id,'professional_id'=>$doctor['id'],'service_unit_id'=>$unit['id'],'scheduled_at'=>date('Y-m-d',strtotime('-'.(5+$index%25).' days')).' 10:00:00','status'=>'attended']);
 }
 $s->execute('UPDATE appointments SET service_unit_id=? WHERE company_id=? AND service_unit_id IS NULL',[$unit['id'],$c]);
 // Remove repetitive fixture prose, preserving records and authorship in this isolated dataset.
 foreach($s->all('SELECT e.id,e.notes FROM clinical_evolutions e JOIN care_plans p ON p.id=e.plan_id WHERE p.company_id=?',[$c]) as $e)if(str_contains(Crypto::decrypt($e['notes']),'Texto demonstrativo sem dados reais.'))$s->execute('UPDATE clinical_evolutions SET notes=? WHERE id=?',[Crypto::encrypt('Conversa fictícia sobre a rotina de trabalho. Pactuado retorno com a equipe de referência e revisão das dúvidas no próximo encontro.'),$e['id']]);
});
(new NursingWorklistService(new CareService($s,$nurse)))->sync();
echo "100 pessoas sintéticas carregadas, com equipe, unidade, risco, retorno e histórico variados.\n";
