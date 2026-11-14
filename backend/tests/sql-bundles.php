<?php
declare(strict_types=1);
$host=getenv('MYSQL_TEST_HOST')?:'127.0.0.1';$port=getenv('MYSQL_TEST_PORT')?:'3307';
$user=getenv('MYSQL_TEST_USER')?:'root';$password=getenv('MYSQL_TEST_PASSWORD')?:'';
$db=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$checks=0;
function statements(PDO $db,string $sql): void
{
    $sql=preg_replace('/^--.*$/m','',$sql);
    foreach(explode(';',$sql) as $statement) if(trim($statement)) $db->exec($statement);
}
foreach(['fresh','upgrade'] as $mode) {
    $name='viva_elo_test_'.bin2hex(random_bytes(6));
    if(!preg_match('/^viva_elo_test_[a-f0-9]{12}$/D',$name)) throw new RuntimeException('Unsafe test database');
    $db->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$db->exec("USE `$name`");
    try {
        if($mode==='upgrade') {
            $db->exec('CREATE TABLE schema_migrations (name VARCHAR(190) PRIMARY KEY,applied_at DATETIME NOT NULL)');
            foreach(glob(dirname(__DIR__).'/database/migrations/00[1-4]_*.sql') as $migration) {statements($db,file_get_contents($migration));$db->prepare('INSERT INTO schema_migrations VALUES (?,?)')->execute([basename($migration),date('Y-m-d H:i:s')]);}
            $db->exec("INSERT INTO companies(name) VALUES ('Preservar este registro')");
        }
        $file=dirname(__DIR__).'/database/'.($mode==='fresh'?'viva_elo.sql':'upgrade_industry.sql');
        $sql=file_get_contents($file);
        // Only redirect the bundle's database selection to this isolated fixture.
        $sql=preg_replace('/^CREATE DATABASE IF NOT EXISTS viva_elo[^;]*;/m','',$sql);
        $sql=str_replace('USE viva_elo;',"USE `$name`;",$sql);
        statements($db,$sql);
        if((int)$db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn()!==count(glob(dirname(__DIR__).'/database/migrations/*.sql'))) throw new RuntimeException('Incomplete migration ledger');$checks++;
        $db->query('SELECT source_revision FROM care_pending_items LIMIT 1');$checks++;
        $db->query('SELECT milestone,points FROM worker_journey_awards LIMIT 1');$checks++;
        $db->query('SELECT management_level FROM users LIMIT 1');$checks++;
        $db->query('SELECT content_version FROM content_progress LIMIT 1');$checks++;
        $db->query('SELECT version FROM service_contracts LIMIT 1');$checks++;
        if($mode==='upgrade') {if($db->query('SELECT name FROM companies WHERE id=1')->fetchColumn()!=='Preservar este registro') throw new RuntimeException('Upgrade lost existing data');$checks++;}
    } finally {$db->exec("DROP DATABASE `$name`");}
}
echo "OK: {$checks} SQL bundle checks on MySQL\n";
