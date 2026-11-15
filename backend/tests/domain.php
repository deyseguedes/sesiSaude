<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Repositories\Store;
use App\Services\Access;
use App\Core\HttpException;

$mysqlDsn=getenv('TEST_MYSQL_DSN');
$db = $mysqlDsn ? new PDO($mysqlDsn,getenv('MYSQL_TEST_USER')?:'root',getenv('MYSQL_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]) : new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(!$mysqlDsn) $db->exec('PRAGMA foreign_keys=ON');
foreach (glob(dirname(__DIR__).'/database/migrations/*.sql') as $file) {
    $sql = file_get_contents($file);
    if(!$mysqlDsn) {
        $sql=str_replace('INTEGER PRIMARY KEY AUTO_INCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT',$sql);
        // SQLite TEXT is already unbounded; MySQL needs the explicit capacity upgrade.
        $sql=str_replace('ALTER TABLE aps_imports MODIFY COLUMN report LONGTEXT NOT NULL;','',$sql);
    }
    foreach(explode(';',$sql) as $statement) if(trim($statement)) $db->exec($statement);
}
$s = new Store($db); $count = 0;
function verify(bool $pass, string $name): void { global $count; $count++; if (!$pass) throw new RuntimeException($name); }
function denied(callable $call, int $status): void { try { $call(); } catch (HttpException $e) { verify($e->status===$status, 'Expected denial '.$status); return; } throw new RuntimeException('Access unexpectedly granted'); }
foreach (['A','B'] as $name) $s->insert('companies',['name'=>$name]);
foreach ([['doctor',1],['nurse',1],['navigator',1],['manager',1],['admin',1],['worker',1],['doctor',2]] as $i=>[$role,$company]) {
    $s->insert('users',['name'=>$role,'email'=>$i.'@test.invalid','password_hash'=>'unused','role'=>$role,'company_id'=>$company]);
}
$s->insert('teams',['company_id'=>1,'name'=>'APS A']); $s->insert('teams',['company_id'=>2,'name'=>'APS B']);
foreach ([1,2,3] as $user) $s->insert('team_members',['team_id'=>1,'user_id'=>$user]);
$s->insert('team_members',['team_id'=>2,'user_id'=>7]);
foreach ([[1,'Linked'],[1,'Unlinked'],[2,'Other company']] as [$company,$name]) $s->insert('patients',['company_id'=>$company,'name'=>$name]);
$s->insert('care_team_assignments',['company_id'=>1,'patient_id'=>1,'team_id'=>1]);
$s->insert('care_team_assignments',['company_id'=>2,'patient_id'=>3,'team_id'=>2]);
$doctor = $s->one('SELECT * FROM users WHERE id=1');
$access = new Access($s,$doctor);
verify(count($access->patients())===1, 'Patient scope');
verify($access->patient(1)['name']==='Linked','Linked patient');
denied(fn()=>$access->patient(2),404); denied(fn()=>$access->patient(3),404);
foreach ([3,4,5,6] as $id) denied(fn()=>(new Access($s,$s->one('SELECT * FROM users WHERE id=?',[$id])))->patient(1),403);
verify((new Access($s,$s->one('SELECT * FROM users WHERE id=3')))->patient(1,false)['id']===1,'Navigator administrative scope');
denied(fn()=>$access->team(1,2,7),422);
$access->team(1,1,2);
$_ENV['APP_KEY']=base64_encode(random_bytes(32));
$care=new App\Services\CareService($s,$doctor);
$data=['patient_id'=>1,'professional_id'=>1,'team_id'=>1,'start_date'=>date('Y-m-d'),'reassessment_date'=>date('Y-m-d',time()+86400),'objectives'=>'Objetivo fictício','interventions'=>'Ação pactuada','clinical_notes'=>'Nota privada','status'=>'active'];
$plan=$care->create($data)['id'];
verify($care->detail($plan)['clinical_notes']==='Nota privada','Encrypted notes roundtrip');
verify($s->one('SELECT clinical_notes FROM care_plans WHERE id=?',[$plan])['clinical_notes']!=='Nota privada','Notes encrypted at rest');
denied(fn()=>(new App\Services\CareService($s,$s->one('SELECT * FROM users WHERE id=7')))->detail($plan),404);
$care->update($plan,$data+['version'=>1]);
denied(fn()=>$care->update($plan,$data+['version'=>1]),409);
denied(fn()=>$care->create(array_replace($data,['patient_id'=>2])),404);
$care->review($plan,['notes'=>'Revisão humana','outcome'=>'reviewed','reassessment_date'=>date('Y-m-d',time()+172800),'version'=>2]);
verify(count($care->detail($plan)['reviews'])===1,'Review persisted');
verify(count($care->detail($plan)['events'])===3,'Audit event history');
$indicators=new App\Services\IndicatorService($care);
$indicator=$indicators->create(['patient_id'=>1,'name'=>'Indicador fictício','type'=>'numeric','unit'=>'unidade','reference_value'=>'Definido pelo profissional','measurement_method'=>'Medição demonstrativa','frequency'=>'Conforme plano'])['id'];
$goalData=['indicator_id'=>$indicator,'description'=>'Meta demonstrativa','initial_value'=>1,'target_value'=>5,'direction'=>'increase','frequency'=>'Semanal','deadline'=>date('Y-m-d',time()+86400),'responsible_id'=>1,'status'=>'active','min_samples'=>2,'measurement_source'=>'professional'];
$goal=$indicators->goal($plan,$goalData)['id'];
$s->execute('UPDATE care_plan_goals SET updated_at=? WHERE id=?',[date('Y-m-d H:i:s',time()-3600),$goal]);
verify($indicators->goals($plan)[0]['progress']['state']==='insufficient_data','No measurement cannot attain goal');
$indicators->measure($indicator,['value'=>8,'source'=>'self_reported','collected_at'=>date('Y-m-d H:i:s',time()-60),'method'=>'Autorrelato']);
verify($indicators->goals($plan)[0]['progress']['samples']===0,'Self-report excluded from professional goal');
$indicators->measure($indicator,['value'=>8,'source'=>'professional','collected_at'=>date('Y-m-d H:i:s',time()-50),'method'=>'Medição demonstrativa']);
verify($indicators->goals($plan)[0]['progress']['state']==='insufficient_data','Minimum sample rule');
$indicators->measure($indicator,['value'=>8,'source'=>'professional','collected_at'=>date('Y-m-d H:i:s',time()-40),'method'=>'Medição demonstrativa']);
verify($indicators->goals($plan)[0]['progress']['state']==='attained','Sufficient measured target');
$indicators->goal($plan,array_replace($goalData,['target_value'=>10,'version'=>1]),$goal);
verify($indicators->goals($plan)[0]['progress']['state']==='insufficient_data','Target change resets evidence window');
denied(fn()=>$indicators->measure($indicator,['value'=>'invalid','source'=>'professional','collected_at'=>date('Y-m-d H:i:s'),'method'=>'Teste']),422);
denied(fn()=>(new App\Services\IndicatorService(new App\Services\CareService($s,$s->one('SELECT * FROM users WHERE id=7'))))->measurements($indicator),404);
$category=$indicators->create(['patient_id'=>1,'name'=>'Categoria fictícia','type'=>'categorical','unit'=>'categoria','measurement_method'=>'Autorrelato','frequency'=>'Mensal','categories'=>['A','B']])['id'];
denied(fn()=>$indicators->measure($category,['value'=>'C','source'=>'self_reported','collected_at'=>date('Y-m-d H:i:s'),'method'=>'Autorrelato']),422);
$s->insert('appointments',['company_id'=>1,'patient_id'=>1,'professional_id'=>1,'scheduled_at'=>date('Y-m-d').' 09:00:00']);
$continuity=new App\Services\ContinuityService($care);
verify($continuity->sync()['created']===1,'Unconfirmed return creates case');
verify($continuity->sync()['created']===0,'Synchronization is idempotent');
$case=$continuity->cases()[0];
$navCare=new App\Services\CareService($s,$s->one('SELECT * FROM users WHERE id=3'));
$nav=new App\Services\ContinuityService($navCare);
$nav->action((int)$case['id'],['type'=>'barrier','barrier'=>'transport','version'=>1]);
denied(fn()=>$nav->action((int)$case['id'],['type'=>'confirm','version'=>1]),409);
$nav->action((int)$case['id'],['type'=>'resolve_barrier','version'=>2]);
$nav->action((int)$case['id'],['type'=>'reschedule','date'=>date('Y-m-d',time()+86400),'time'=>'10:00','version'=>3]);
verify(count($continuity->cases())===2,'Reschedule preserves old appointment and creates new case');
verify($nav->detail((int)$case['id'])['barrier_resolved']===1,'Barrier resolution persisted');
verify(!array_key_exists('clinical_notes',$nav->detail((int)$case['id'])),'Administrative projection excludes clinical notes');
denied(fn()=>$nav->referral(['patient_id'=>1,'team_id'=>1,'to_professional_id'=>2,'reason'=>'Restrito']),403);
denied(fn()=>(new App\Services\ContinuityService(new App\Services\CareService($s,$s->one('SELECT * FROM users WHERE id=7'))))->detail((int)$case['id']),404);
verify((int)$s->one('SELECT COUNT(*) n FROM team_notifications')['n']===3,'APS receives administrative outcome');
$dashboard=new App\Services\DashboardService($care);
$stats=$dashboard->clinical('doctor',[]);
verify($stats['metrics']['patients']===1,'Dashboard patient scope');
verify($stats['metrics']['measurements']===3,'Dashboard counts recorded measurements');
verify($stats['metrics']['reviews_completed']===1,'Dashboard review count');
denied(fn()=>$dashboard->clinical('nurse',[]),403);
denied(fn()=>$dashboard->clinical('doctor',['patient_id'=>'3']),404);
denied(fn()=>$dashboard->clinical('doctor',['from'=>'2026-02-30']),422);
$window=['from'=>date('Y-m-d',time()-86400),'to'=>date('Y-m-d',time()+172800)];
$continuityStats=$dashboard->continuity($window);
verify($continuityStats['metrics']['returns_expected']===1,'Rescheduled original excluded from denominator');
verify($continuityStats['metrics']['continuity_rate']===0.0,'Zero confirmed rate');
$newCase=array_values(array_filter($continuity->cases(),fn($c)=>$c['status']==='pending'))[0];
$nav->action((int)$newCase['id'],['type'=>'confirm','version'=>1]);
verify($dashboard->continuity($window)['metrics']['continuity_rate']===100.0,'Confirmation updates continuity rate');
verify((new App\Services\DashboardService(new App\Services\CareService($s,$s->one('SELECT * FROM users WHERE id=4'))))->portal()['suppressed']===true,'Small employer cohorts suppressed');
$s->execute('UPDATE care_team_assignments SET active=0 WHERE patient_id=1');
denied(fn()=>$care->detail($plan),404);
verify($dashboard->clinical('doctor',[])['metrics']['patients']===0,'Revoked assignment immediately removes data');
$s->execute('UPDATE care_team_assignments SET active=1 WHERE patient_id=1');
foreach(['decrease','range','maintain','category'] as $direction) {
    $categorical=$direction==='category';
    $i=$indicators->create(['patient_id'=>1,'name'=>'Teste '.$direction,'type'=>$categorical?'categorical':'numeric','unit'=>'fictícia','measurement_method'=>'Teste','frequency'=>'Pactuada','categories'=>$categorical?['A','B']:[]])['id'];
    $g=$indicators->goal($plan,array_replace($goalData,['indicator_id'=>$i,'initial_value'=>$categorical?'A':0,'target_value'=>$categorical?'B':3,'target_min'=>1,'target_max'=>5,'direction'=>$direction,'min_samples'=>1]))['id'];
    $indicators->measure($i,['value'=>$categorical?'B':3,'source'=>'professional','collected_at'=>date('Y-m-d H:i:s'),'method'=>'Teste']);
    $row=$s->one('SELECT * FROM care_plan_goals WHERE id=?',[$g]);
    verify($indicators->progress($row)['state']==='attained','Goal evaluator '.$direction);
}
denied(fn()=>$indicators->measure($indicator,['value'=>1,'source'=>'professional','collected_at'=>date('Y-m-d H:i:s',time()+86400),'method'=>'Teste']),422);
denied(fn()=>(new App\Services\IndicatorService($navCare))->measurements($indicator),403);
echo "OK: {$count} domain checks\n";
