<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Session};
use App\Repositories\Store;
final class DemoWorkspace {
 public static function key():string {
  if(!LocalProfileAccess::publicDemo())return 'operation';
  Session::start();$_SESSION['demo_visit']??=bin2hex(random_bytes(20));return $_SESSION['demo_visit'];
 }
 public static function reset():void {
  if(!LocalProfileAccess::publicDemo())throw new HttpException(404,'NOT_FOUND','Disponível somente na demonstração.');
  Session::start();$_SESSION['demo_visit']=bin2hex(random_bytes(20));$_SESSION['demo_started_at']=date('Y-m-d H:i:s');
 }
 public static function people(Store $s,int $company):array {
  $root=dirname(__DIR__,3);if(in_array(basename($root),['public-demo','server-release'],true))$root=dirname($root);$snapshot=$root.'/dados-evento/demo-snapshot.enc';
  $rows=$s->all('SELECT * FROM aps_people WHERE company_id=? AND visit_key=? ORDER BY id',[$company,self::key()]);
  if(!$rows && LocalProfileAccess::publicDemo())$rows=$s->all('SELECT * FROM aps_people WHERE company_id=? AND visit_key=? AND test_data=1 ORDER BY id',[$company,'template']);
  if(LocalProfileAccess::publicDemo() && !array_filter($rows,fn($r)=>$r['visit_key']===self::key()) && is_file($snapshot)){
   $saved=json_decode(Crypto::decrypt(file_get_contents($snapshot)),true,512,JSON_THROW_ON_ERROR);
   if(is_array($saved))$rows=array_values(array_filter($saved,fn($r)=>(int)($r['company_id']??0)===$company&&(int)($r['test_data']??0)===1));
  }
  if(LocalProfileAccess::publicDemo()&&!in_array('DEMO-0001',array_column($rows,'registration'),true)){$marcos=$s->one('SELECT * FROM aps_people WHERE company_id=? AND visit_key=? AND registration=? AND test_data=1',[$company,'template','DEMO-0001']);if($marcos)$rows[]=$marcos;}
  foreach($rows as &$row)$row['data']=json_decode(Crypto::decrypt($row['payload']),true,512,JSON_THROW_ON_ERROR);unset($row);return $rows;
 }
}
