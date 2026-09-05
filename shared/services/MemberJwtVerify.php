<?php

declare(strict_types=1);

/**
 * Frontend-safe JWT signature check (no PDO, no issue/revoke).
 * Used by BackendMemberApiProxy on API-only hosts where MemberJwtService is excluded.
 */
final class MemberJwtVerify
{
    public static function signatureValid(string $jwt): bool
    {
        if ($jwt === '') {
            return false;
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        [$header, $payload, $signature] = $parts;
        $rawSignature = self::b64Dec($signature);
        if (!is_string($rawSignature)) {
            return false;
        }
        try {
            $secret = self::secret();
            if ($secret === '') {
                return false;
            }
            $expected = hash_hmac('sha256', $header . '.' . $payload, $secret, true);
        } catch (Throwable) {
            return false;
        }

        return hash_equals($expected, $rawSignature);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function payloadIfValid(string $jwt): ?array
    {
        if (!self::signatureValid($jwt)) {
            return null;
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payloadJson = self::b64Dec($parts[1]);
        if (!is_string($payloadJson)) {
            return null;
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }
        $exp = (int) ($payload['exp'] ?? 0);
        $uid = (int) ($payload['uid'] ?? $payload['sub'] ?? 0);
        if ($uid <= 0 || ($exp > 0 && $exp < time())) {
            return null;
        }

        return $payload;
    }

    public static function userIdFromJwt(string $jwt): int
    {
        $payload = self::payloadIfValid($jwt);

        return is_array($payload) ? (int) ($payload['uid'] ?? $payload['sub'] ?? 0) : 0;
    }

    private static function secret(): string
    {
        if (defined('MEMBER_JWT_SECRET') && trim((string) MEMBER_JWT_SECRET) !== '') {
            return trim((string) MEMBER_JWT_SECRET);
        }
        $fromEnv = trim((string) (getenv('MEMBER_JWT_SECRET') ?: ''));

        return $fromEnv;
    }

    private static function b64Dec(string $raw): ?string
    {
        $padLen = 4 - (strlen($raw) % 4);
        if ($padLen > 0 && $padLen < 4) {
            $raw .= str_repeat('=', $padLen);
        }
        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
