<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Database,HttpException,Identity,Input,Response,Session};
use App\Repositories\Store;
use App\Services\{BeneficiaryService,EngagementService,EnrollmentService,IndustryService,SchedulingService};
final class OperationsController
{
    private Store $store;private array $user;
    public function __construct()
    {
        Session::start();if(!isset($_SESSION['user_id'])) throw new HttpException(401,'UNAUTHENTICATED','Autenticação necessária.');
        $this->store=new Store(Database::connect());$this->user=Identity::require($this->store);
    }
    private function industry(): IndustryService
    {
        $company=null;if(isset($_GET['company_id'])) {if(!ctype_digit((string)$_GET['company_id']) || (int)$_GET['company_id']<1) Input::fail('empresa');$company=(int)$_GET['company_id'];}
        return new IndustryService($this->store,$this->user,$company);
    }
    private function scheduling(): SchedulingService {return new SchedulingService($this->store,$this->user);}
    private function engagement(): EngagementService {return new EngagementService($this->store,$this->user);}
    public function companies(): void {Response::send(200,$this->industry()->companies());}
    public function createCompany(): void {Response::send(201,$this->industry()->createCompany(Input::body()));}
    public function overview(): void {Response::send(200,$this->industry()->overview());}
    public function unit(): void {Response::send(201,$this->industry()->unit(Input::body()));}
    public function serviceUnit(): void {Response::send(201,$this->industry()->unit(Input::body(),true));}
    public function contract(): void {Response::send(201,$this->industry()->contract(Input::body()));}
    public function updateContract(int $id): void {Response::send(200,$this->industry()->contract(Input::body(),$id));}
    public function account(): void {Response::send(201,$this->industry()->account(Input::body()));}
    public function updateAccount(int $id): void {Response::send(200,$this->industry()->updateAccount($id,Input::body()));}
    public function team(): void {Response::send(201,$this->industry()->team(Input::body()));}
    public function member(): void {Response::send(201,$this->industry()->member(Input::body()));}
    public function invite(): void {Response::send(201,(new EnrollmentService($this->store))->invite($this->industry(),Input::body()));}
    public function validate(int $id): void {Response::send(200,(new EnrollmentService($this->store))->validate($this->industry(),$id,Input::body()));}
    public function dependent(): void {Response::send(201,(new EnrollmentService($this->store))->representedDependent($this->industry(),Input::body()));}
    public function ownInvite(int $id): void {Response::send(201,(new EnrollmentService($this->store))->invite(new IndustryService($this->store,$this->user),Input::body(),$id));}
    public function beneficiaries(): void {Response::send(200,(new BeneficiaryService($this->store,$this->user))->list());}
    public function appointments(): void {Response::send(200,(new BeneficiaryService($this->store,$this->user))->appointments());}
    public function confirm(int $id): void {Response::send(200,(new BeneficiaryService($this->store,$this->user))->confirm($id));}
    public function support(int $id): void {Response::send(201,(new BeneficiaryService($this->store,$this->user))->support($id,Input::body()));}
    public function slots(): void {Response::send(200,$this->scheduling()->slots($this->industry()));}
    public function createSlot(): void {Response::send(201,$this->scheduling()->createSlot($this->industry(),Input::body()));}
    public function blockSlot(int $id): void {Response::send(200,$this->scheduling()->block($this->industry(),$id,Input::body()));}
    public function search(): void {Response::send(200,$this->scheduling()->search($_GET));}
    public function book(int $id): void {Response::send(201,$this->scheduling()->book($id,Input::body()));}
    public function setAccess(int $id): void {Response::send(200,$this->scheduling()->setAccess($id,Input::body()));}
    public function access(int $id): void {Response::send(200,$this->scheduling()->access($id));}
    public function contents(): void {Response::send(200,$this->engagement()->contents($this->industry()));}
    public function createContent(): void {Response::send(201,$this->engagement()->content($this->industry(),Input::body()));}
    public function updateContent(int $id): void {Response::send(200,$this->engagement()->content($this->industry(),Input::body(),$id));}
    public function publishContent(int $id): void {Response::send(200,$this->engagement()->publish($this->industry(),$id,Input::body()));}
    public function library(): void {Response::send(200,$this->engagement()->library());}
    public function complete(int $id): void {Response::send(200,$this->engagement()->complete($id));}
    public function campaigns(): void {Response::send(200,$this->engagement()->campaigns($this->industry()));}
    public function createCampaign(): void {Response::send(201,$this->engagement()->createCampaign($this->industry(),Input::body()));}
    public function campaignStatus(int $id): void {Response::send(200,$this->engagement()->campaignStatus($this->industry(),$id,Input::body()));}
    public function ownCampaigns(): void {Response::send(200,$this->engagement()->mine());}
    public function ownStage(int $id): void {Response::send(200,$this->engagement()->selfStage($id,Input::body()));}
    public function pipeline(): void {Response::send(200,$this->engagement()->pipeline());}
    public function stage(int $id): void {Response::send(200,$this->engagement()->staffStage($id,Input::body()));}
    public function task(int $id): void {Response::send(201,$this->engagement()->task($id,Input::body()));}
    public function updateTask(int $id): void {Response::send(200,$this->engagement()->updateTask($id,Input::body()));}
    public function assignees(int $id): void {Response::send(200,$this->engagement()->assignees($id));}
    public function professionalAgenda(): void {Response::send(200,$this->scheduling()->professionalAgenda());}
    public function assignment(): void {Response::send(200,$this->industry()->assignment(Input::body()));}
}
