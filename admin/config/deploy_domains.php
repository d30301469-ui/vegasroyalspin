<?php

declare(strict_types=1);

/**
 * Production domain defaults — vegasroyalspin.com (frontend) + bo-nexthub.site (backend).
 * Override via .env (SITE_URL, FRONTEND_URL, PUBLIC_URL_HOSTS, …).
 */
if (!function_exists('deploy_domain_config')) {
    /**
     * @return array{
     *   frontend_url: string,
     *   frontend_fallback_url: string,
     *   mobile_url: string,
     *   backend_url: string,
     *   backend_api_base_url: string,
     *   public_url_hosts: string,
     *   allowed_url_hosts: string,
     *   default_allowed_url_hosts: string,
     *   session_cookie_domain: string,
     *   api_public_base_url: string,
     *   api_subdomain_host: string
     * }
     */
    function deploy_domain_config(): array
    {
        static $config = null;
        if (is_array($config)) {
            return $config;
        }

        return $config = [
            'frontend_url' => 'https://vegasroyalspin.com',
            'frontend_fallback_url' => 'https://vegasroyalspin.com',
            'mobile_url' => 'https://m.vegasroyalspin.com',
            'backend_url' => 'https://bo-nexthub.site',
            'backend_api_base_url' => 'https://api.bo-nexthub.site/api/v2',
            'public_url_hosts' => 'vegasroyalspin.com,www.vegasroyalspin.com,m.vegasroyalspin.com',
            'allowed_url_hosts' => 'vegasroyalspin.com,www.vegasroyalspin.com,m.vegasroyalspin.com,bo-nexthub.site,api.bo-nexthub.site',
            'default_allowed_url_hosts' => 'vegasroyalspin.com,www.vegasroyalspin.com,m.vegasroyalspin.com,bo-nexthub.site,api.bo-nexthub.site',
            'session_cookie_domain' => '.vegasroyalspin.com',
            'api_public_base_url' => 'https://api.bo-nexthub.site/api/v2',
            'api_subdomain_host' => 'api.bo-nexthub.site',
        ];
    }

    function deploy_domain(string $key, string $default = ''): string
    {
        $config = deploy_domain_config();

        return (string) ($config[$key] ?? $default);
    }

    /**
     * Backend panel + member API subdomain hostları (küçük harf).
     *
     * @return list<string>
     */
    function deploy_backend_hosts(): array
    {
        $hosts = [];
        foreach ([deploy_domain('backend_url'), deploy_domain('api_subdomain_host')] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            $host = parse_url($candidate, PHP_URL_HOST);
            $host = is_string($host) && $host !== '' ? $host : $candidate;
            $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? '');
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * Apache RewriteCond için backend host regex (port opsiyonel).
     */
    function deploy_backend_hosts_htaccess_regex(): string
    {
        $parts = array_map(
            static fn (string $host): string => preg_quote($host, '/'),
            deploy_backend_hosts()
        );
        if ($parts === []) {
            return 'bo-nexthub\.site|api\.bo-nexthub\.site';
        }

        return implode('|', $parts);
    }

    function deploy_is_backend_host(string $httpHost): bool
    {
        $host = strtolower(preg_replace('/:\d+$/', '', trim($httpHost)) ?? '');
        if ($host === '') {
            return false;
        }

        return in_array($host, deploy_backend_hosts(), true);
    }

    /**
     * Ana frontend hostundan www + m varyantlarını üretir.
     */
    function deploy_frontend_host_variants(?string $frontendUrl = null): string
    {
        $url = trim((string) ($frontendUrl ?? deploy_domain('frontend_url')));
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: 'vegasroyalspin.com'));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        if (str_starts_with($host, 'm.')) {
            $host = substr($host, 2);
        }
        if ($host === '') {
            return deploy_domain('public_url_hosts');
        }

        $hosts = array_unique([$host, 'www.' . $host, 'm.' . $host]);

        return implode(',', $hosts);
    }

    /**
     * Frontend + backend hostları (CORS / redirect güvenliği).
     */
    function deploy_allowed_url_hosts(?string $frontendUrl = null, ?string $backendUrl = null): string
    {
        $frontendHosts = deploy_frontend_host_variants($frontendUrl);
        $backendHost = strtolower((string) (parse_url((string) ($backendUrl ?? deploy_domain('backend_url')), PHP_URL_HOST) ?: 'bo-nexthub.site'));
        $apiHost = strtolower((string) (parse_url(deploy_domain('api_public_base_url'), PHP_URL_HOST) ?: deploy_domain('api_subdomain_host')));
        $parts = array_filter(array_merge(
            array_map('trim', explode(',', $frontendHosts)),
            $backendHost !== '' ? [$backendHost] : [],
            $apiHost !== '' ? [$apiHost] : [],
            deploy_backend_hosts()
        ));

        return implode(',', array_unique($parts));
    }

    function deploy_session_cookie_domain_for_host(string $httpHost): string
    {
        $host = strtolower(preg_replace('/:\d+$/', '', trim($httpHost)) ?? '');
        if ($host === '' || !str_ends_with($host, 'vegasroyalspin.com')) {
            return '';
        }

        return deploy_domain('session_cookie_domain');
    }

    /**
     * Eski DB/CMS URL'lerinde yeniden yazılacak hostlar (.test, localhost, bilinen eski dev hostları).
     *
     * @return list<string>
     */
    function deploy_stale_url_hosts(): array
    {
        return [
            'admin.metropolcasino.test',
            'metropolcasino.test',
            'm.metropolcasino.test',
            'bo-metropolcasino.site',
            'maltabet.test',
        ];
    }
}
