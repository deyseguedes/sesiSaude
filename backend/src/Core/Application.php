<?php
declare(strict_types=1);

namespace App\Core;

use App\Middleware\SecurityHeaders;
use Throwable;

final class Application
{
    public function __construct(private readonly array $routes) {}

    public function run(): void
    {
        SecurityHeaders::apply();
        try {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $route = null; $params = [];
            foreach ($this->routes as $pattern => $candidate) {
                $regex = '#^' . preg_replace('/\\{id\\}/', '([1-9][0-9]*)', $pattern) . '$#D';
                if (is_string($path) && preg_match($regex, $path, $matches)) { $route = $candidate; $params = array_map('intval', array_slice($matches, 1)); break; }
            }
            if ($route === null) {
                Response::send(404, error: ['code' => 'NOT_FOUND', 'message' => 'Recurso não encontrado.']);
                return;
            }
            if (!isset($route[$method])) {
                header('Allow: ' . implode(', ', array_keys($route)));
                Response::send(405, error: ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Método não permitido.']);
                return;
            }
            [$controller, $action] = $route[$method];
            (new $controller())->$action(...$params);
        } catch (HttpException $exception) {
            Response::send($exception->status, error: ['code' => $exception->errorCode, 'message' => $exception->getMessage()]);
        } catch (\PDOException $exception) {
            if (in_array((string)$exception->getCode(),['23000','40001'],true)) {
                Response::send(409,error:['code'=>'CONFLICT','message'=>'Conflito de integridade. Recarregue antes de tentar novamente.']);
            } else {
                error_log('API database failure: '.$exception::class);
                Response::send(500,error:['code'=>'INTERNAL_ERROR','message'=>'Erro interno.']);
            }
        } catch (Throwable $exception) {
            // Do not log request bodies, credentials or exception messages containing SQL.
            error_log('API failure: ' . $exception::class);
            Response::send(500, error: ['code' => 'INTERNAL_ERROR', 'message' => 'Erro interno.']);
        }
    }
}
