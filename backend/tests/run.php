<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
$routes = require dirname(__DIR__) . '/routes/api.php';
$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function request(string $method, string $path): array
{
    global $routes;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $path;
    ob_start();
    (new Application($routes))->run();
    $body = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
    return [http_response_code(), $body];
}

[$status, $body] = request('GET', '/api/v1/health?probe=1');
check($status === 200 && $body['data']['status'] === 'ok' && $body['error'] === null, 'Health contract');
foreach (['/', '/api/v1/not-implemented', '/.env', '/vendor/autoload.php'] as $path) {
    [$status, $body] = request('GET', $path);
    check($status === 404 && $body['data'] === null && $body['error']['code'] === 'NOT_FOUND', 'Unknown route: ' . $path);
}
[$status, $body] = request('POST', '/api/v1/health');
check($status === 405 && $body['error']['code'] === 'METHOD_NOT_ALLOWED', 'Method restriction');
[$status, $body] = request('GET', '/api/v1/clinical/patients');
check($status === 401, 'Anonymous clinical access denied');
$routes['/failure'] = ['GET' => ['MissingController', 'show']];
[$status, $body] = request('GET', '/failure');
check($status === 500 && $body['error']['message'] === 'Erro interno.', 'Safe internal error');
foreach ([
    ['local','true','127.0.0.1',true],
    ['local','true','::1',true],
    ['production','true','127.0.0.1',false],
    ['local','false','127.0.0.1',false],
    ['local','true','192.168.1.10',false],
    ['','true','127.0.0.1',false],
] as [$environment,$enabled,$address,$expected]) {
    $_ENV['APP_ENV']=$environment; $_ENV['LOCAL_PROFILE_ACCESS']=$enabled; $_SERVER['REMOTE_ADDR']=$address;
    check(\App\Services\LocalProfileAccess::enabled()===$expected,'Local profile access policy');
}
$_ENV['APP_ENV']='local';$_ENV['LOCAL_PROFILE_ACCESS']='true';$_SERVER['REMOTE_ADDR']='127.0.0.1';
foreach(['VIVA_PUBLIC_ACCESS','REDIRECT_VIVA_PUBLIC_ACCESS','HTTP_X_FORWARDED_FOR','HTTP_FORWARDED'] as $flag) {
    $_SERVER[$flag]='1';check(!\App\Services\LocalProfileAccess::enabled(),'Public gateway and proxy cannot use local identities');unset($_SERVER[$flag]);
}
check(\App\Services\LocalProfileAccess::enabled(),'Direct local profiles remain available');
$_ENV = [];
try {
    Database::connect();
    throw new LogicException('Missing config accepted');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Database configuration is incomplete.', 'Required database config');
}
echo "OK: {$checks} checks\n";
