<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Database,Identity,Input,Response,Session};
use App\Repositories\Store;
use App\Services\DemoExperienceService;
final class DemoExperienceController {
 private DemoExperienceService $service;
 public function __construct(){Session::start();$s=new Store(Database::connect());$this->service=new DemoExperienceService($s,Identity::require($s));}
 public function leader():void{Response::send(200,$this->service->leader());}
 public function channel():void{Response::send(200,$this->service->channel());}
 public function reply():void{Response::send(200,$this->service->reply(Input::body()));}
 public function management():void{Response::send(200,$this->service->management());}
}
