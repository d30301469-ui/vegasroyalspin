<?php

declare(strict_types=1);

final class AdminRouter
{
    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = [];

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    private function add(string $method, string $path, array $handler): void
    {
        $normalized = '/' . trim($path, '/');
        $normalized = $normalized === '/' ? '/' : rtrim($normalized, '/');
        $this->routes[strtoupper($method)][$normalized] = $handler;
    }

    public function dispatch(string $path, string $method): void
    {
        $method = strtoupper($method);
        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            http_response_code(404);
            (new AdminController())->view('errors/404', ['title' => 'Sayfa bulunamadı'], 'app');
            return;
        }

        [$controllerClass, $action] = $handler;
        $controller = new $controllerClass();
        try {
            $controller->$action();
        } catch (InvalidArgumentException $exception) {
            http_response_code(404);
            (new AdminController())->view('errors/404', [
                'title' => 'Sayfa bulunamadı',
                'errorMessage' => $exception->getMessage(),
            ], 'app');
        } catch (Throwable $exception) {
            http_response_code(500);
            (new AdminController())->view('errors/500', [
                'title' => 'Sunucu hatası',
                'errorMessage' => $exception->getMessage(),
            ], 'app');
        }
    }
}
