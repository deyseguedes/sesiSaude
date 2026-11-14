<?php
declare(strict_types=1);
namespace App\Core;
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        if (($_ENV['APP_ENV'] ?? 'local') === 'production' && ($_SERVER['HTTPS'] ?? '') !== 'on') {
            throw new HttpException(400, 'HTTPS_REQUIRED', 'HTTPS obrigatório.');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        $directory=dirname(__DIR__,2).'/storage/sessions';
        if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new \RuntimeException('Session storage unavailable');
        session_save_path($directory);
        session_name('viva_elo');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => ($_SERVER['HTTPS'] ?? '') === 'on', 'httponly' => true, 'samesite' => 'Strict']);
        if (!session_start()) throw new \RuntimeException('Session initialization failed');
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 1800) $_SESSION = [];
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
    public static function csrf(): void
    {
        if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            throw new HttpException(403, 'CSRF_INVALID', 'Sessão inválida. Atualize a página.');
        }
    }
}
