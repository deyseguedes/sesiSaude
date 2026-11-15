<?php
declare(strict_types=1);
require dirname(__DIR__).'/public-demo/backend/environment.php';
use App\Services\{LocalProfileAccess,Crypto};
if(PHP_SAPI!=='cli'||!LocalProfileAccess::publicDemo())throw new RuntimeException('Snapshot somente do banco fictício; execute com o ambiente da demonstração.');
$s=new App\Repositories\Store(App\Core\Database::connect());$visit=$argv[1]??'template';
if($visit!=='template'&&!preg_match('/^[a-f0-9]{40}$/D',$visit))throw new RuntimeException('ID de visita inválido.');
$rows=$s->all('SELECT * FROM aps_people WHERE visit_key=? AND test_data=1 ORDER BY id',[$visit]);
if(!$rows)throw new RuntimeException('Nenhum dado de teste para salvar.');
$directory=dirname(__DIR__).'/dados-evento';if(!is_dir($directory))mkdir($directory,0700,true);
file_put_contents($directory.'/demo-snapshot.enc',Crypto::encrypt(json_encode($rows,JSON_THROW_ON_ERROR)),LOCK_EX);
echo "Snapshot cifrado salvo fora do Git. Não inclui tabelas legadas.\n";
