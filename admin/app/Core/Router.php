<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use Throwable;

final class Router
{
    /** @var array<string, array<int, array{pattern:string, handler:callable, middleware:array<int, callable>}>> */
    private array $routes = [];

    /** @param array<int, callable> $middleware */
    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        $method = strtoupper($method);
        $pattern = '/' . trim($pattern, '/');
        $pattern = $pattern === '/' ? '/' : rtrim($pattern, '/');
        $this->routes[$method][] = [
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /** @param array<int, callable> $middleware */
    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /** @param array<int, callable> $middleware */
    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /** @param array<int, callable> $middleware */
    public function any(string $pattern, callable $handler, array $middleware = []): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $this->add($method, $pattern, $handler, $middleware);
        }
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();
        foreach ($this->routes[$method] ?? [] as $route) {
            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }

            $handler = $route['handler'];
            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                static fn (callable $next, callable $middleware): Closure => static fn (Request $request) => $middleware($request, $next),
                static fn (Request $request) => $handler($request, $params)
            );

            try {
                $pipeline($request);
            } catch (Throwable $exception) {
                self::serverError($exception);
            }
            return;
        }

        Response::html('<h1>404 - Sayfa bulunamadı</h1>', 404);
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        if ($pattern === $path || $pattern === '/{any}') {
            return [];
        }
        if (str_ends_with($pattern, '/{any}')) {
            $prefix = substr($pattern, 0, -strlen('/{any}'));
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return ['any' => ltrim(substr($path, strlen($prefix)), '/')];
            }
        }

        return null;
    }

    private static function serverError(Throwable $exception): void
    {
        error_log($exception->getMessage());
        Response::html('<h1>500 - Sunucu hatası</h1>', 500);
    }
}

