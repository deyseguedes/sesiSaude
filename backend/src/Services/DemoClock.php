<?php
declare(strict_types=1);
namespace App\Services;
final class DemoClock {
 public static function now():string{return LocalProfileAccess::publicDemo()&&isset($_SESSION['demo_clock'])?$_SESSION['demo_clock']:date('Y-m-d H:i:s');}
 public static function today():string{return substr(self::now(),0,10);}
 public static function recording(\App\Repositories\Store $s,int $company):array {
  if(!LocalProfileAccess::publicDemo())throw new \App\Core\HttpException(404,'NOT_FOUND','Somente demonstração.');
  foreach(DemoWorkspace::people($s,$company) as $r)if($r['registration']==='DEMO-0001'){$_SESSION['demo_clock']=(new \DateTimeImmutable($r['data']['return_date']))->modify('-10 days')->format('Y-m-d').' 14:30:00';break;}return ['clock'=>self::now()];
 }
}
