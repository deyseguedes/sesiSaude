<?php
declare(strict_types=1);
require dirname(__DIR__).'/environment.php';
$s=new App\Repositories\Store(App\Core\Database::connect()); $total=0;
foreach($s->all('SELECT id,role,company_id FROM users WHERE active=1 AND role IN (?,?,?)',['doctor','nurse','navigator']) as $user) {
    $service=new App\Services\ContinuityService(new App\Services\CareService($s,$user));
    $total+=$service->sync()['created'];
}
echo "Pendências criadas: {$total}\n";
