<?php
declare(strict_types=1);
namespace App\Core;
use App\Repositories\Store;
final class Identity
{
    public static function require(Store $store): array
    {
        Session::start();
        if(!isset($_SESSION['user_id'])) throw new HttpException(401,'UNAUTHENTICATED','Autenticação necessária.');
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET') Session::csrf();
        $user=$store->one('SELECT id,name,role,company_id,management_level FROM users WHERE id=? AND active=1',[$_SESSION['user_id']]);
        if(!$user) throw new HttpException(401,'UNAUTHENTICATED','Sessão inválida.');
        \App\Services\Audit::record($store,$user,'api_'.strtolower($_SERVER['REQUEST_METHOD']??'GET'),substr((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),0,80),0);
        return $user;
    }
}
