<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/environment.php';
if(($_ENV['APP_ENV']??'')!=='local') throw new RuntimeException('Cenário permitido somente no ambiente local.');
$fixtureStore=new App\Repositories\Store(App\Core\Database::connect());
$fixtureDoctor=$fixtureStore->one('SELECT * FROM users WHERE email=? AND role=? AND active=1',['doctor@demo.vivaelo.test','doctor']);
$fixtureNurse=$fixtureStore->one('SELECT * FROM users WHERE email=? AND role=? AND active=1',['nurse@demo.vivaelo.test','nurse']);
if(!$fixtureDoctor || !$fixtureNurse || $fixtureDoctor['company_id']!==$fixtureNurse['company_id']) throw new RuntimeException('Instale primeiro a carga de demonstração.');
if($fixtureStore->one('SELECT id FROM patients WHERE name=? AND company_id=?',['Maria — cenário fictício de enfermagem',$fixtureDoctor['company_id']])) { echo "Cenário Maria já existente; nenhum registro alterado.\n";return; }
$fixtureTeam=$fixtureStore->one('SELECT t.id FROM teams t JOIN team_members d ON d.team_id=t.id JOIN team_members n ON n.team_id=t.id WHERE t.company_id=? AND d.user_id=? AND n.user_id=?',[$fixtureDoctor['company_id'],$fixtureDoctor['id'],$fixtureNurse['id']]);
if(!$fixtureTeam) throw new RuntimeException('Equipe de demonstração indisponível.');
$fixtureStore->transaction(function()use($fixtureStore,$fixtureDoctor,$fixtureNurse,$fixtureTeam){
    $patient=$fixtureStore->insert('patients',['company_id'=>$fixtureDoctor['company_id'],'name'=>'Maria — cenário fictício de enfermagem']);
    $fixtureStore->insert('care_team_assignments',['company_id'=>$fixtureDoctor['company_id'],'patient_id'=>$patient,'team_id'=>$fixtureTeam['id']]);
    $care=new App\Services\CareService($fixtureStore,$fixtureDoctor);
    $fixtureStore->insert('appointments',['company_id'=>$fixtureDoctor['company_id'],'patient_id'=>$patient,'professional_id'=>$fixtureDoctor['id'],'scheduled_at'=>date('Y-m-d H:i:s',time()-7*86400),'status'=>'attended']);
    $care->create(['patient_id'=>$patient,'professional_id'=>(int)$fixtureDoctor['id'],'team_id'=>(int)$fixtureTeam['id'],'start_date'=>date('Y-m-d',time()-7*86400),'reassessment_date'=>date('Y-m-d',time()+2*86400),'objectives'=>'Pactuar acompanhamento compatível com a rotina de trabalho no cenário fictício.','interventions'=>'Conferir disponibilidade para orientação educativa e retorno.','status'=>'active','clinical_notes'=>'Cenário inteiramente fictício, sem aplicação clínica.']);
    $continuity=new App\Services\ContinuityService($care);
    $appointment=$continuity->schedule($patient,['team_id'=>(int)$fixtureTeam['id'],'professional_id'=>(int)$fixtureDoctor['id'],'date'=>date('Y-m-d',time()+2*86400),'time'=>'16:47']);
    $case=$fixtureStore->one('SELECT * FROM continuity_cases WHERE id=?',[$appointment['case_id']]);
    (new App\Services\ContinuityService(new App\Services\CareService($fixtureStore,$fixtureNurse)))->action((int)$case['id'],['type'=>'barrier','version'=>(int)$case['version'],'barrier'=>'schedule','administrative_note'=>'Maria informou voluntariamente incompatibilidade com o horário de trabalho e deseja contato humano. Registro fictício.']);
});
echo "Cenário Maria criado: comparecimento anterior, plano e dificuldade de horário.\n";
