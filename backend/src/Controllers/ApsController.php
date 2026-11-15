<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Database,Identity,Input,Response,Session};
use App\Repositories\Store;
use App\Services\{ApsImportService,DemoWorkspace};
final class ApsController {
 private ApsImportService $service;
 private Store $store;private array $user;
 public function __construct(){Session::start();$s=new Store(Database::connect());$this->store=$s;$this->user=Identity::require($s);$this->service=new ApsImportService($s,$this->user);}
 public function people():void{$this->service->authorize();$rows=DemoWorkspace::people($this->store,(int)$this->user['company_id']);foreach($rows as &$r){$r=$r['data'];$r['cpf']=empty($r['cpf'])?'':'***.***.***-'.substr($r['cpf'],-2);$r['phone']=empty($r['phone'])?'':'(**) *****-'.substr(preg_replace('/\D/','',$r['phone']),-4);}Response::send(200,$rows);}
 public function import():void{$this->service->authorize();Response::send(200,$this->service->import(Input::body(12582912)));}
 public function last():void{Response::send(200,$this->service->last());}
 public function recording():void{$d=str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')?Input::body():[];Response::send(200,\App\Services\DemoClock::recording($this->store,(int)$this->user['company_id'],($d['enabled']??true)!==false));}
 public function reset():void{Session::csrf();DemoWorkspace::reset();Response::send(200,['reset'=>true,'scope'=>'roteiro']);}
}
