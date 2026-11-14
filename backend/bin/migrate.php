<?php
declare(strict_types=1);
require dirname(__DIR__) . '/environment.php';
$db = App\Core\Database::connect();
$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL)');
foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') as $file) {
    $name = basename($file);
    $check = $db->prepare('SELECT name FROM schema_migrations WHERE name=?'); $check->execute([$name]);
    if ($check->fetch()) continue;
    // MySQL DDL commits implicitly. Never mark a partially failed migration as applied.
    foreach (explode(';', file_get_contents($file)) as $sql) if (trim($sql)) $db->exec($sql);
    $db->prepare('INSERT INTO schema_migrations VALUES (?,?)')->execute([$name,date('Y-m-d H:i:s')]);
    echo "Applied {$name}\n";
}
