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
 public function import():void{$this->service->authorize();Response::send(200,$this->service->fileImport(Input::body(12582912)));}
 public function inspect():void{$this->service->authorize();$data=Input::body(12582912);$data['preview']=true;Response::send(200,$this->service->inspectFile($data));}
 public function reviewDetail(int $id):void{$service=new \App\Services\ApsReviewService($this->store,$this->user);Response::send(200,$service->detail($id));}
 public function reviews():void{$service=new \App\Services\ApsReviewService($this->store,$this->user);Response::send(200,$service->listing($_GET));}
 public function review(int $id):void{$service=new \App\Services\ApsReviewService($this->store,$this->user);Response::send(200,$service->update($id,Input::body()));}
 public function last():void{Response::send(200,$this->service->last());}
 public function recording():void{$d=str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')?Input::body():[];Response::send(200,\App\Services\DemoClock::recording($this->store,(int)$this->user['company_id'],($d['enabled']??true)!==false));}
 public function reset():void{Session::csrf();DemoWorkspace::reset();Response::send(200,['reset'=>true,'scope'=>'roteiro']);}
}
