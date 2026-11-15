<?php
declare(strict_types=1);

namespace App\Services;

final class HealthService
{
    public function status(): array
    {
        return ['service' => 'Life+ API', 'status' => 'ok', 'version' => '3', 'phase' => 'F'];
    }
}
