<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Database,Identity,Input,Response,Session};
use App\Repositories\Store;
use App\Services\{OutreachService,CampaignExperimentService};
final class CampaignExperimentController {
 private CampaignExperimentService $service;
 public function __construct(){Session::start();$s=new Store(Database::connect());$this->service=new CampaignExperimentService(new OutreachService($s,Identity::require($s)));}
 public function listing():void{Response::send(200,$this->service->listing());}
 public function detail(int $id):void{Response::send(200,$this->service->detail($id));}
 public function create():void{Response::send(201,$this->service->create(Input::body()));}
 public function start(int $id):void{Response::send(200,$this->service->start($id));}
}
