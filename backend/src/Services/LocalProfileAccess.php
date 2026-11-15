<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\Store;

final class LocalProfileAccess
{
    public const ROLES = ['admin','manager','doctor','nurse','navigator','worker','dependent'];

    public static function publicDemo(): bool
    {
        return ($_ENV['APP_ENV'] ?? '') === 'demo'
            && ($_ENV['PUBLIC_DEMO_ACCESS'] ?? '') === 'true'
            && preg_match('/^viva_elo_demo_[a-f0-9]{12}$/D',$_ENV['DB_DATABASE'] ?? '') === 1;
    }
    public static function enabled(): bool
    {
        if(self::publicDemo()) return true;
        return ($_ENV['APP_ENV'] ?? '') === 'local'
            && ($_ENV['LOCAL_PROFILE_ACCESS'] ?? '') === 'true'
            && ($_SERVER['VIVA_PUBLIC_ACCESS'] ?? '') !== '1'
            && ($_SERVER['REDIRECT_VIVA_PUBLIC_ACCESS'] ?? '') !== '1'
            && !isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            && !isset($_SERVER['HTTP_FORWARDED'])
            && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);
    }

    public static function profiles(Store $store): array
    {
        if (!self::enabled()) return [];
        $available = [];
        foreach (self::ROLES as $role) {
            if (self::account($store, $role)) $available[] = $role;
        }
        return $available;
    }

    public static function account(Store $store, string $role): ?array
    {
        if (!self::enabled() || !in_array($role, self::ROLES, true)) return null;
        // Only explicitly seeded demonstration accounts; never select an arbitrary real user.
        return $store->one('SELECT id,name,role,company_id,management_level FROM users WHERE email=? AND role=? AND active=1', [$role.'@demo.vivaelo.test', $role]);
    }
}
