<?php

declare(strict_types=1);

/**
 * Frontend ↔ backend trust helpers (split-host JWT mint / proxy).
 */
if (!function_exists('memberFrontendTrustIsPlaceholderSecret')) {
    function memberFrontendTrustIsPlaceholderSecret(string $value): bool
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '' || strlen($normalized) < 32) {
            return true;
        }
        foreach (['change-me', 'changeme', 'example', 'placeholder', 'default'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('memberFrontendTrustSecretCandidates')) {
    /** @return list<string> */
    function memberFrontendTrustSecretCandidates(): array
    {
        $candidates = [];
        $dedicated = trim((string) (getenv('FRONTEND_MEMBER_TRUST_SECRET') ?: ''));
        if ($dedicated !== '' && !memberFrontendTrustIsPlaceholderSecret($dedicated)) {
            $candidates[] = $dedicated;
        }

        foreach ([
            trim((string) (getenv('FRONTEND_CMS_PURGE_SECRET') ?: '')),
            defined('FRONTEND_CMS_PURGE_SECRET') ? trim((string) FRONTEND_CMS_PURGE_SECRET) : '',
        ] as $legacy) {
            if ($legacy !== '' && !memberFrontendTrustIsPlaceholderSecret($legacy) && !in_array($legacy, $candidates, true)) {
                $candidates[] = $legacy;
            }
        }

        return $candidates;
    }
}

if (!function_exists('memberFrontendTrustClientIp')) {
    function memberFrontendTrustClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $raw = trim((string) ($_SERVER[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (str_contains($raw, ',')) {
                $raw = trim(explode(',', $raw, 2)[0]);
            }
            if (filter_var($raw, FILTER_VALIDATE_IP) !== false) {
                return $raw;
            }
        }

        return '0.0.0.0';
    }
}

if (!function_exists('memberFrontendTrustIpAllowed')) {
    function memberFrontendTrustIpAllowed(): bool
    {
        $clientIp = memberFrontendTrustClientIp();
        if (in_array($clientIp, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        $allowlist = trim((string) (getenv('FRONTEND_TRUST_IP_ALLOWLIST') ?: ''));
        if ($allowlist === '') {
            // HMAC secret zorunlu; IP listesi opsiyonel sıkılaştırma katmanı.
            return true;
        }

        foreach (preg_split('/\s*,\s*/', $allowlist) ?: [] as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }
            if ($entry === $clientIp) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('memberFrontendTrustVerify')) {
    function memberFrontendTrustVerify(int $userId, string $trust, string $scope = 'member-jwt'): bool
    {
        if ($userId <= 0 || $trust === '') {
            return false;
        }
        if (!memberFrontendTrustIpAllowed()) {
            return false;
        }
        foreach (memberFrontendTrustSecretCandidates() as $candidate) {
            if (hash_equals(hash_hmac('sha256', $scope . ':' . $userId, $candidate), $trust)) {
                return true;
            }
        }

        return false;
    }
}
