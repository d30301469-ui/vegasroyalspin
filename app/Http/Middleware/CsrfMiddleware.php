<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware
{
    public function __invoke(Request $request, callable $next): void
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $next($request);
            return;
        }

        $key = (string) Config::get('security.csrf_key', (string) (getenv('CSRF_TOKEN_KEY') ?: 'site_csrf_token'));
        if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        $payload = $request->isJson() ? $request->json() : $_POST;
        $token = (string) (
            $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $payload['_token']
            ?? $payload['csrf_token']
            ?? ''
        );

        if ($token === '' || !hash_equals((string) $_SESSION[$key], $token)) {
            Response::json([
                'success' => false,
                'code' => 403,
                'message' => 'CSRF doğrulaması başarısız.',
            ], 403);
            return;
        }

        $next($request);
    }
}

