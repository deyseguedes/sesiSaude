<?php
declare(strict_types=1);
require dirname(__DIR__) . '/environment.php';
if (($_ENV['APP_ENV'] ?? '') !== 'local') throw new RuntimeException('Carga fictícia permitida apenas em APP_ENV=local.');
$password = getenv('DEMO_PASSWORD');
if (!$password || strlen($password) < 12 || strlen($password)>72) throw new RuntimeException('Defina DEMO_PASSWORD entre 12 e 72 bytes.');
App\Services\Crypto::encrypt('Validate demo encryption before writing records.');
$store = new App\Repositories\Store(App\Core\Database::connect());
if ($store->one('SELECT id FROM companies LIMIT 1')) throw new RuntimeException('Carga exige banco vazio. Nenhum registro alterado.');
$store->transaction(function () use ($store,$password) {
    $company = $store->insert('companies',['name'=>'Empresa fictícia Aurora']);
    $team = $store->insert('teams',['company_id'=>$company,'name'=>'APS Aurora']);
    foreach (['admin'=>'Administrador SESI','worker'=>'Trabalhador','navigator'=>'Navegador de Cuidado','manager'=>'Gestor empresarial','doctor'=>'Médica','nurse'=>'Enfermeira'] as $role=>$name) {
        $id = $store->insert('users',['company_id'=>$company,'name'=>$name . ' — demonstração','email'=>$role.'@demo.vivaelo.test','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role]);
        if (in_array($role,['doctor','nurse','navigator'],true)) $store->insert('team_members',['team_id'=>$team,'user_id'=>$id]);
        if ($role==='worker') $worker=$id;
        if ($role==='doctor') $doctor=$id;
    }
    foreach (['Ana Fictícia','Bruno Fictício','Carla Fictícia'] as $i=>$name) {
        $patient=$store->insert('patients',['company_id'=>$company,'user_id'=>$i===0?$worker:null,'name'=>$name]);
        $store->insert('care_team_assignments',['company_id'=>$company,'patient_id'=>$patient,'team_id'=>$team]);
        $store->insert('appointments',['company_id'=>$company,'patient_id'=>$patient,'professional_id'=>$doctor,'scheduled_at'=>date('Y-m-d').' 09:00:00','status'=>'expected']);
    }
});
$doctor=$store->one('SELECT * FROM users WHERE role=? ORDER BY id LIMIT 1',['doctor']);
$demoTeam=(int)$store->one('SELECT id FROM teams WHERE company_id=? ORDER BY id LIMIT 1',[$doctor['company_id']])['id'];
$care=new App\Services\CareService($store,$doctor); $engine=new App\Services\IndicatorService($care);
foreach($care->access->patients() as $patient) {
    $plan=$care->create(['patient_id'=>(int)$patient['id'],'professional_id'=>(int)$doctor['id'],'team_id'=>$demoTeam,'start_date'=>date('Y-m-d',time()-7*86400),'reassessment_date'=>date('Y-m-d',time()+3*86400),'objectives'=>'Acompanhar participação em ações educativas fictícias.','interventions'=>'Pactuar atividades com a pessoa acompanhada.','status'=>'active','clinical_notes'=>'Registro fictício para demonstração do acesso restrito.'])['id'];
    $indicator=$engine->create(['patient_id'=>(int)$patient['id'],'name'=>'Participação educativa fictícia','type'=>'frequency','unit'=>'atividades','reference_value'=>'Exemplo fictício pactuado, sem aplicação clínica.','measurement_method'=>'Registro demonstrativo','frequency'=>'Semanal'])['id'];
    $goal=$engine->goal($plan,['indicator_id'=>$indicator,'description'=>'Participação pactuada na demonstração','initial_value'=>0,'target_value'=>3,'direction'=>'increase','measurement_source'=>'professional','frequency'=>'Semanal','deadline'=>date('Y-m-d',time()+7*86400),'responsible_id'=>(int)$doctor['id'],'status'=>'active','min_samples'=>2])['id'];
    // Explicit fictional chronology, solely for meaningful demonstration charts.
    $past=date('Y-m-d H:i:s',time()-7*86400);
    $store->execute('UPDATE care_plans SET created_at=? WHERE id=?',[$past,$plan]);
    $store->execute('UPDATE care_plan_goals SET created_at=?,updated_at=? WHERE id=?',[$past,$past,$goal]);
    foreach([5=>1,3=>2,1=>3] as $days=>$value) $engine->measure($indicator,['value'=>$value,'source'=>'professional','collected_at'=>date('Y-m-d H:i:s',time()-$days*86400),'method'=>'Registro demonstrativo']);
    $engine->measure($indicator,['value'=>2,'source'=>'self_reported','collected_at'=>date('Y-m-d H:i:s',time()-2*86400),'method'=>'Autorrelato fictício']);
}
(new App\Services\ContinuityService($care))->sync();
require __DIR__.'/seed-industry.php';
echo "Carga fictícia criada. Contas: {admin,worker,navigator,manager,doctor,nurse}@demo.vivaelo.test\n";
