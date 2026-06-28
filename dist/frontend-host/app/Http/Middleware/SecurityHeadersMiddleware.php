<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;

final class SecurityHeadersMiddleware
{
    public function __invoke(Request $request, callable $next): void
    {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
            header("Content-Security-Policy: object-src 'none'; base-uri 'self'; frame-ancestors 'self'");

            $this->applyCorsHeaders();
        }

        // Handle OPTIONS preflight immediately — no further processing needed.
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $next($request);
    }

    private function applyCorsHeaders(): void
    {
        $allowedOrigins = $this->allowedOrigins();
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));

        if ($origin === '') {
            return;
        }

        if (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-CSRF-Token, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        header('Vary: Origin');
    }

    /** @return list<string> */
    private function allowedOrigins(): array
    {
        $raw = (string) (getenv('ALLOWED_URL_HOSTS') ?: (defined('ALLOWED_URL_HOSTS') ? ALLOWED_URL_HOSTS : ''));
        $hosts = array_filter(array_map('trim', explode(',', $raw)));

        $origins = [];
        foreach ($hosts as $host) {
            $origins[] = 'https://' . $host;
            $origins[] = 'http://' . $host;
        }

        $frontendUrl = rtrim((string) (getenv('FRONTEND_URL') ?: (defined('FRONTEND_URL') ? FRONTEND_URL : '')), '/');
        if ($frontendUrl !== '') {
            $origins[] = $frontendUrl;
        }

        return array_unique(array_filter($origins));
    }
}


