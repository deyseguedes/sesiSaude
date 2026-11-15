<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Database,HttpException,Response,Session};
use App\Repositories\Store;
use App\Services\Audit;
use App\Services\LocalProfileAccess;
final class AuthController
{
    public function session(): void
    {
        Session::start();
        $user = null;
        if (isset($_SESSION['user_id'])) {
            $user = (new Store(Database::connect()))->one('SELECT id,name,role,company_id,management_level FROM users WHERE id=? AND active=1', [$_SESSION['user_id']]);
        }
        $profiles = LocalProfileAccess::enabled() ? LocalProfileAccess::profiles(new Store(Database::connect())) : [];
        Response::send(200, ['user' => $user, 'csrf_token' => $_SESSION['csrf'], 'local_profiles' => $profiles, 'public_demo' => LocalProfileAccess::publicDemo()]);
    }
    public function localProfile(): void
    {
        if (!LocalProfileAccess::enabled()) throw new HttpException(404, 'NOT_FOUND', 'Recurso não encontrado.');
        Session::start(); Session::csrf();
        $input = \App\Core\Input::body();
        if (!is_string($input['role'] ?? null) || !in_array($input['role'], LocalProfileAccess::ROLES, true)) throw new HttpException(422, 'VALIDATION_ERROR', 'Escolha um perfil disponível.');
        $store = new Store(Database::connect());
        $user = LocalProfileAccess::account($store, $input['role']);
        if (!$user) throw new HttpException(404, 'NOT_FOUND', 'Perfil local indisponível.');
        Audit::record($store, $user, 'local_profile_login', 'user', (int)$user['id']);
        session_regenerate_id(true); $_SESSION['user_id'] = (int)$user['id']; $_SESSION['csrf'] = bin2hex(random_bytes(32));
        Response::send(200, ['user' => $user, 'csrf_token' => $_SESSION['csrf']]);
    }
    public function login(): void
    {
        Session::start(); Session::csrf();
        $input = \App\Core\Input::body();
        if (!is_array($input) || !is_string($input['email'] ?? null) || strlen($input['email'])>190 || !filter_var($input['email'],FILTER_VALIDATE_EMAIL) || !is_string($input['password'] ?? null) || strlen($input['password']) > 72) throw new HttpException(422, 'VALIDATION_ERROR', 'Informe e-mail e senha válidos.');
        $store = new Store(Database::connect());
        $key = hash('sha256', strtolower($input['email']) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $attempt = $store->one('SELECT COUNT(*) AS n FROM login_attempts WHERE attempt_key=? AND created_at>=?', [$key, date('Y-m-d H:i:s', time()-900)]);
        if ((int)$attempt['n'] >= 10) throw new HttpException(429, 'RATE_LIMITED', 'Aguarde antes de tentar novamente.');
        $store->insert('login_attempts', ['attempt_key' => $key, 'created_at' => date('Y-m-d H:i:s')]);
        $user = $store->one('SELECT * FROM users WHERE email=? AND active=1', [strtolower(trim($input['email']))]);
        if (!$user || !password_verify($input['password'], $user['password_hash'])) throw new HttpException(401, 'INVALID_CREDENTIALS', 'Credenciais inválidas.');
        Audit::record($store, $user, 'login', 'user', (int)$user['id']);
        session_regenerate_id(true); $_SESSION['user_id'] = (int)$user['id']; $_SESSION['csrf'] = bin2hex(random_bytes(32));
        unset($user['password_hash'], $user['active'], $user['email']);
        Response::send(200, ['user' => $user, 'csrf_token' => $_SESSION['csrf']]);
    }
    public function logout(): void
    {
        Session::start(); Session::csrf(); $_SESSION = []; session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        Response::send(200, ['user' => null, 'csrf_token' => $_SESSION['csrf']]);
    }
}
