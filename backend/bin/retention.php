<?php
declare(strict_types=1);
require dirname(__DIR__).'/environment.php';
$s=new App\Repositories\Store(App\Core\Database::connect());
// Demo operational policy only. Clinical history is never purged by this command.
$cutoff=date('Y-m-d H:i:s',time()-30*86400);
$count=(int)$s->one('SELECT COUNT(*) n FROM login_attempts WHERE created_at<?',[$cutoff])['n'];
echo "Tentativas de login com mais de 30 dias: {$count}\n";
if(in_array('--apply',$argv,true)) $s->execute('DELETE FROM login_attempts WHERE created_at<?',[$cutoff]);
echo "Prontuários, planos, medições e auditoria preservados.\n";
