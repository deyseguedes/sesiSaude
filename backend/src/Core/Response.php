<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function send(int $status, mixed $data = null, ?array $error = null): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $data, 'error' => $error], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
