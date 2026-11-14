<?php
declare(strict_types=1);

namespace App\Middleware;

final class SecurityHeaders
{
    public static function apply(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        header('Referrer-Policy: no-referrer');
        if(($_ENV['APP_ENV']??'local')==='production') header('Strict-Transport-Security: max-age=31536000');
    }
}
