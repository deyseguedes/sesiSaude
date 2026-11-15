<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/environment.php';
use App\Repositories\Store;
use App\Services\{IndustryService,EnrollmentService,SchedulingService,EngagementService};
if(($_ENV['APP_ENV']??'')!=='local') throw new RuntimeException('Somente demonstração local.');
$store=new Store(App\Core\Database::connect());
$demoAdmin=$store->one('SELECT u.* FROM users u JOIN companies c ON c.id=u.company_id WHERE u.email=? AND c.name=?',['admin@demo.vivaelo.test','Empresa fictícia Aurora']);
if(!$demoAdmin) throw new RuntimeException('Carga complementar exige a base fictícia Aurora. Nenhuma base real será adaptada.');
App\Services\Crypto::encrypt('Validate demo key');
$demoCompany=(int)$demoAdmin['company_id'];
$store->execute('UPDATE users SET management_level=? WHERE id=?',['platform',$demoAdmin['id']]);
$store->execute('UPDATE users SET management_level=? WHERE company_id=? AND email=?',['company',$demoCompany,'manager@demo.vivaelo.test']);
$store->execute('UPDATE users SET management_level=? WHERE company_id=? AND email=?',['coordination',$demoCompany,'nurse@demo.vivaelo.test']);
if(!$store->one('SELECT user_id FROM management_company_access WHERE user_id=? AND company_id=?',[$demoAdmin['id'],$demoCompany])) $store->insert('management_company_access',['user_id'=>$demoAdmin['id'],'company_id'=>$demoCompany]);
$demoAdmin['management_level']='platform';$industry=new IndustryService($store,$demoAdmin);
$unit=$store->one('SELECT id FROM industry_units WHERE company_id=? AND name=?',[$demoCompany,'Fábrica Aurora — fictícia']);
$demoUnit=$unit?(int)$unit['id']:$industry->unit(['name'=>'Fábrica Aurora — fictícia','address'=>'Endereço demonstrativo · Salvador','postal_code'=>'40000-000','latitude'=>-12.97,'longitude'=>-38.50])['id'];
$contract=$store->one('SELECT id FROM service_contracts WHERE company_id=? AND name=?',[$demoCompany,'Contrato APS e família — fictício']);
$demoContract=$contract?(int)$contract['id']:$industry->contract(['name'=>'Contrato APS e família — fictício','start_date'=>date('Y-m-d',time()-86400),'end_date'=>date('Y-m-d',time()+180*86400),'status'=>'active','allow_in_person'=>true,'allow_telehealth'=>true,'allow_dependents'=>true])['id'];
$clinic=$store->one('SELECT id FROM service_units WHERE company_id=? AND name=?',[$demoCompany,'Unidade SESI — demonstração']);
$demoClinic=$clinic?(int)$clinic['id']:$industry->unit(['name'=>'Unidade SESI — demonstração','kind'=>'sesi','address'=>'Local fictício · Salvador','postal_code'=>'40000-001','latitude'=>-12.98,'longitude'=>-38.51],true)['id'];
foreach($store->all('SELECT id FROM patients WHERE company_id=?',[$demoCompany]) as $patient) {
    if(!$store->one('SELECT id FROM beneficiary_links WHERE patient_id=?',[$patient['id']])) $store->insert('beneficiary_links',['patient_id'=>$patient['id'],'company_id'=>$demoCompany,'contract_id'=>$demoContract,'industry_unit_id'=>$demoUnit,'kind'=>'worker','status'=>'active','created_at'=>date('Y-m-d H:i:s')]);
}
$demoWorker=$store->one('SELECT p.id FROM patients p JOIN users u ON u.id=p.user_id WHERE u.email=? AND p.company_id=?',['worker@demo.vivaelo.test',$demoCompany]);
$dependent=$store->one('SELECT id FROM users WHERE email=?',['dependent@demo.vivaelo.test']);
if(!$dependent) {
    $password=getenv('DEMO_PASSWORD');if(!$password || strlen($password)<12 || strlen($password)>72) throw new RuntimeException('Defina DEMO_PASSWORD (12–72 bytes) para a conta fictícia do dependente.');
    $store->transaction(function()use($store,$demoCompany,$demoContract,$demoUnit,$demoWorker,$password){
        $user=$store->insert('users',['company_id'=>$demoCompany,'name'=>'Daniela Dependente Fictícia','email'=>'dependent@demo.vivaelo.test','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'dependent']);
        $patient=$store->insert('patients',['company_id'=>$demoCompany,'user_id'=>$user,'name'=>'Daniela Dependente Fictícia']);
        $store->insert('beneficiary_links',['patient_id'=>$patient,'company_id'=>$demoCompany,'contract_id'=>$demoContract,'industry_unit_id'=>$demoUnit,'kind'=>'dependent','sponsor_patient_id'=>$demoWorker['id'],'status'=>'active','created_at'=>date('Y-m-d H:i:s')]);
    });
}
$demoDoctor=$store->one('SELECT id FROM users WHERE email=? AND company_id=?',['doctor@demo.vivaelo.test',$demoCompany]);
$demoTeam=$store->one('SELECT m.team_id FROM team_members m JOIN teams t ON t.id=m.team_id WHERE m.user_id=? AND t.company_id=?',[$demoDoctor['id'],$demoCompany]);
$scheduler=new SchedulingService($store,$demoAdmin);
foreach([1,2,3] as $day) foreach(['in_person'=>'09:00:00','telehealth'=>'10:00:00'] as $modality=>$time) {
    $start=date('Y-m-d',time()+$day*86400).' '.$time;
    if(!$store->one('SELECT id FROM appointment_slots WHERE professional_id=? AND starts_at=?',[$demoDoctor['id'],$start])) $scheduler->createSlot($industry,['professional_id'=>(int)$demoDoctor['id'],'team_id'=>(int)$demoTeam['team_id'],'service_unit_id'=>$demoClinic,'modality'=>$modality,'starts_at'=>$start,'ends_at'=>date('Y-m-d H:i:s',strtotime($start)+1800)]);
}
$editor=new EngagementService($store,$demoAdmin);
$tutorial=$store->one('SELECT id FROM education_contents WHERE company_id=? AND title=?',[$demoCompany,'Como encontrar seu próximo atendimento']);
if(!$tutorial) {
    $tutorialId=$editor->content($industry,['title'=>'Como encontrar seu próximo atendimento','summary'=>'Um passo a passo curto para usar a plataforma sem pressa.','transcript'=>"1. Entre no seu espaço e abra Encontrar atendimento.\n2. Escolha quem será atendido e a modalidade disponível no contrato.\n3. Busque perto da indústria, pela sua localização ou pelo primeiro horário.\n4. Confira os dados e confirme o vínculo com a equipe antes de agendar.\n5. Seus retornos ficam na mesma página. Se precisar reorganizar um horário, peça apoio.\nDemonstração fictícia: roteiro de aproximadamente 90 segundos, sem gravação anexada.",'category'=>'platform','video_url'=>'','duration_seconds'=>90])['id'];
    $editor->publish($industry,$tutorialId,['version'=>1,'review_note'=>'Tutorial fictício revisado para demonstração da plataforma.']);
} else $tutorialId=(int)$tutorial['id'];
if(!$store->one('SELECT id FROM education_contents WHERE company_id=? AND title=?',[$demoCompany,'Seu cuidado também merece espaço'])) $editor->content($industry,['title'=>'Seu cuidado também merece espaço','summary'=>'Uma conversa acolhedora sobre rotina, retornos e apoio da equipe APS.','transcript'=>"A rotina da indústria pode exigir muito. Você não precisa enfrentar sozinho as dificuldades para continuar seu acompanhamento.\nConsulte os próximos retornos e converse com a equipe quando precisar de apoio. Horário, transporte e outras barreiras podem ser acolhidos.\nEste é um roteiro inicial para revisão profissional e gravação. Não oferece diagnóstico ou conduta individual.",'category'=>'aps','video_url'=>'','duration_seconds'=>90]);
$campaign=$store->one('SELECT * FROM campaigns WHERE company_id=? AND title IN (?,?) ORDER BY id LIMIT 1',[$demoCompany,'Meu primeiro passo no Life+','Meu primeiro passo no VIVA+ ELO']);
if($campaign&&$campaign['title']!=='Meu primeiro passo no Life+')$store->execute('UPDATE campaigns SET title=? WHERE id=?',['Meu primeiro passo no Life+',$campaign['id']]);
if(!$campaign) {
    $campaignId=$editor->createCampaign($industry,['title'=>'Meu primeiro passo no Life+','objective'=>'Conhecer a plataforma, localizar os próximos passos e saber como pedir apoio.','cycle_label'=>'Ciclo 1 · conhecer e acolher','start_date'=>date('Y-m-d'),'end_date'=>date('Y-m-d',time()+30*86400),'industry_unit_id'=>$demoUnit,'audience'=>'all','content_ids'=>[$tutorialId]])['id'];
    $editor->campaignStatus($industry,$campaignId,['status'=>'active','version'=>1]);
}
echo "Demonstração industrial pronta: gestão, dependente, agendas, tutorial e campanha.\n";
