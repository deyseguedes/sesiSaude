<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Database,Identity,Input,Response,Session};
use App\Services\OutreachService;
use App\Repositories\Store;
final class OutreachController {
 private OutreachService $service;
 public function __construct(){Session::start();$s=new Store(Database::connect());$this->service=new OutreachService($s,Identity::require($s));}
 public function dashboard():void{Response::send(200,(new \App\Services\SuccessDashboard($this->service))->read($_GET));}
 public function export():void{Response::send(200,$this->service->export());}
 public function phone():void{Response::send(200,$this->service->phone());}
 public function reply():void{Response::send(200,$this->service->reply(Input::body()));}
 public function queue():void{Response::send(200,$this->service->queue());}
}
