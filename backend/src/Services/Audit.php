<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Store;
final class Audit
{
    public static function record(Store $store, array $user, string $event, string $resource, int $id): void
    {
        $store->insert('audit_logs', ['company_id' => $user['company_id'], 'user_id' => $user['id'], 'event' => $event, 'resource_type' => $resource, 'resource_id' => $id, 'created_at' => date('Y-m-d H:i:s')]);
    }
}
