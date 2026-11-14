<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Database,Input,Response,Session};
final class EnrollmentController
{
    public function register(): void
    {
        Session::start(); Session::csrf();
        Response::send(201,(new \App\Services\EnrollmentService(new \App\Repositories\Store(Database::connect())))->register(Input::body()));
    }
}
