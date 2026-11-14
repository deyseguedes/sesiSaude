<?php
declare(strict_types=1);
require_once __DIR__.'/vendor/autoload.php';
foreach(['APP_ENV','APP_KEY','LOCAL_PROFILE_ACCESS','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $key) {
    $value=getenv($key); if($value!==false) $_ENV[$key]=$value;
}
Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
if(($_ENV['APP_ENV']??'local')==='local') $_ENV['DB_PASSWORD']??='';
date_default_timezone_set('America/Bahia');
