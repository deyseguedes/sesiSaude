<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Database,HttpException,Input,Response,Session};
use App\Repositories\Store;
use App\Services\{Audit,CareService};
final class ClinicalController
{
    private CareService $care;
    public function __construct()
    {
        Session::start();
        if (!isset($_SESSION['user_id'])) throw new HttpException(401,'UNAUTHENTICATED','Autenticação necessária.');
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') Session::csrf();
        $s=new Store(Database::connect());
        $u=$s->one('SELECT id,name,role,company_id FROM users WHERE id=? AND active=1',[$_SESSION['user_id']]);
        if (!$u) throw new HttpException(401,'UNAUTHENTICATED','Sessão inválida.');
        Audit::record($s,$u,'api_'.strtolower($_SERVER['REQUEST_METHOD']??'GET'),substr((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),0,80),0);
        $this->care=new CareService($s,$u);
    }
    public function patients(): void { $this->care->access->roles(['doctor','nurse']); Response::send(200,$this->care->access->patients()); }
    public function worklist(): void { Response::send(200,(new \App\Services\NursingWorklistService($this->care))->queue($_GET)); }
    public function syncWorklist(): void { Response::send(200,(new \App\Services\NursingWorklistService($this->care))->sync()); }
    public function worklistDetail(int $id): void { Response::send(200,(new \App\Services\NursingWorklistService($this->care))->detail($id)); }
    public function worklistAction(int $id): void { Response::send(201,(new \App\Services\NursingWorklistService($this->care))->action($id,Input::body())); }
    public function worklistTask(): void { Response::send(201,(new \App\Services\NursingWorklistService($this->care))->create(Input::body())); }
    public function summary(int $id): void { Response::send(200,(new \App\Services\PatientSummaryService($this->care))->summarize($id)); }
    public function patient(int $id): void
    {
        $p=$this->care->access->patient($id); $s=$this->care->store;
        $p['appointments']=$s->all('SELECT id,professional_id,scheduled_at,status FROM appointments WHERE patient_id=? AND company_id=? ORDER BY scheduled_at DESC',[$id,$this->care->user['company_id']]);
        $p['teams']=$s->all('SELECT t.id,t.name FROM teams t JOIN care_team_assignments a ON a.team_id=t.id JOIN team_members m ON m.team_id=t.id WHERE a.patient_id=? AND a.active=1 AND t.company_id=? AND m.user_id=?',[$id,$this->care->user['company_id'],$this->care->user['id']]);
        $p['professionals']=$s->all('SELECT DISTINCT u.id,u.name,u.role,m.team_id FROM users u JOIN team_members m ON m.user_id=u.id JOIN care_team_assignments a ON a.team_id=m.team_id WHERE a.patient_id=? AND a.active=1 AND u.company_id=? AND u.active=1 AND u.role IN (?,?)',[$id,$this->care->user['company_id'],'doctor','nurse']);
        Audit::record($s,$this->care->user,'read','patient',$id); Response::send(200,$p);
    }
    public function plans(): void { Response::send(200,$this->care->plans()); }
    public function create(): void { Response::send(201,$this->care->create(Input::body())); }
    public function detail(int $id): void { Response::send(200,$this->care->detail($id)); }
    public function update(int $id): void { Response::send(200,$this->care->update($id,Input::body())); }
    public function goals(int $id): void { Response::send(200,(new \App\Services\IndicatorService($this->care))->goals($id)); }
    public function createGoal(int $id): void { Response::send(201,(new \App\Services\IndicatorService($this->care))->goal($id,Input::body())); }
    public function updateGoal(int $id): void {
        $this->care->access->roles(['doctor','nurse']);
        $g=$this->care->store->one('SELECT g.plan_id FROM care_plan_goals g JOIN care_plans p ON p.id=g.plan_id WHERE g.id=? AND p.company_id=?',[$id,$this->care->user['company_id']]);
        if (!$g) throw new HttpException(404,'NOT_FOUND','Meta não encontrada.');
        Response::send(200,(new \App\Services\IndicatorService($this->care))->goal((int)$g['plan_id'],Input::body(),$id));
    }
    public function indicators(int $id): void { Response::send(200,(new \App\Services\IndicatorService($this->care))->list($id)); }
    public function createIndicator(): void { Response::send(201,(new \App\Services\IndicatorService($this->care))->create(Input::body())); }
    public function measurements(int $id): void { Response::send(200,(new \App\Services\IndicatorService($this->care))->measurements($id)); }
    public function measure(int $id): void { Response::send(201,(new \App\Services\IndicatorService($this->care))->measure($id,Input::body())); }
    public function review(int $id): void { Response::send(201,$this->care->review($id,Input::body())); }
    public function evolution(int $id): void { Response::send(201,$this->care->evolution($id,Input::body())); }
    public function adherence(int $id): void { Response::send(201,$this->care->adherence($id,Input::body())); }
    public function sync(): void { Response::send(200,(new \App\Services\ContinuityService($this->care))->sync()); }
    public function cases(): void { Response::send(200,(new \App\Services\ContinuityService($this->care))->cases()); }
    public function caseDetail(int $id): void { Response::send(200,(new \App\Services\ContinuityService($this->care))->detail($id)); }
    public function action(int $id): void { Response::send(201,(new \App\Services\ContinuityService($this->care))->action($id,Input::body())); }
    public function referral(): void { Response::send(201,(new \App\Services\ContinuityService($this->care))->referral(Input::body())); }
    public function clinicalDashboard(): void { Response::send(200,(new \App\Services\DashboardService($this->care))->clinical('doctor',$_GET)); }
    public function nursingDashboard(): void { Response::send(200,(new \App\Services\DashboardService($this->care))->clinical('nurse',$_GET)); }
    public function continuityDashboard(): void { Response::send(200,(new \App\Services\DashboardService($this->care))->continuity($_GET)); }
    public function portal(): void { Response::send(200,(new \App\Services\DashboardService($this->care))->portal()); }
    public function schedule(int $id): void { Response::send(201,(new \App\Services\ContinuityService($this->care))->schedule($id,Input::body())); }
}
