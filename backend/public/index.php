<?php
declare(strict_types=1);

ini_set('display_errors', '0');
try {
    $app = require dirname(__DIR__) . '/bootstrap.php';
    $app->run();
} catch (Throwable $exception) {
    error_log('API bootstrap failure: '.$exception::class);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['data'=>null,'error'=>['code'=>'INTERNAL_ERROR','message'=>'Erro interno.']],JSON_UNESCAPED_UNICODE);
}
