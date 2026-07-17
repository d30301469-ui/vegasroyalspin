<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function host(): string
    {
        return strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    }

    public function isAdminHost(): bool
    {
        $host = $this->host();
        if ($host === '') {
            return false;
        }

        return in_array($host, $this->configuredAdminHosts(), true);
    }

    /**
     * @return list<string>
     */
    private function configuredAdminHosts(): array
    {
        $candidates = array_merge(
            [
                Config::get('security.admin_host', 'bo-backoffice.site'),
                getenv('ADMIN_URL_HOST') ?: '',
                getenv('BACKEND_HOST') ?: '',
                parse_url((string) (getenv('BACKEND_URL') ?: ''), PHP_URL_HOST) ?: '',
            ],
            Config::get('security.backend_hosts', function_exists('deploy_backend_hosts') ? deploy_backend_hosts() : ['bo-backoffice.site', 'api.bo-backoffice.site'])
        );

        $hosts = [];
        foreach ($candidates as $candidate) {
            $host = strtolower(preg_replace('/:\d+$/', '', trim((string) $candidate)) ?? '');
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    public function isJson(): bool
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        return str_contains($contentType, 'application/json');
    }

    public function rawBody(): string
    {
        $raw = file_get_contents('php://input');
        return is_string($raw) ? $raw : '';
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $decoded = $this->rawBody() !== '' ? json_decode($this->rawBody(), true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($_SERVER[$key] ?? '');
    }

    public function bearerToken(): string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($header === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                $header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
            }
        }
        if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }
}

