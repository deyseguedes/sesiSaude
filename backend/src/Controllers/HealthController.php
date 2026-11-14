<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\HealthService;

final class HealthController
{
    public function show(): void
    {
        Response::send(200, (new HealthService())->status());
    }
}
