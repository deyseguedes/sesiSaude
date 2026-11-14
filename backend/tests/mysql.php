<?php
declare(strict_types=1);
$host=getenv('MYSQL_TEST_HOST')?:'127.0.0.1'; $port=getenv('MYSQL_TEST_PORT')?:'3307';
$user=getenv('MYSQL_TEST_USER')?:'root'; $password=getenv('MYSQL_TEST_PASSWORD')?:'';
$admin=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='viva_elo_test_'.bin2hex(random_bytes(6));
if(!preg_match('/^viva_elo_test_[a-f0-9]{12}$/D',$name)) throw new RuntimeException('Unsafe test database');
$admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    putenv("TEST_MYSQL_DSN=mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4");
    $process=proc_open([PHP_BINARY,__DIR__.'/patient-summary.php'],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes);
    $code=proc_close($process);
} finally { $admin->exec("DROP DATABASE `$name`"); }
exit($code);
