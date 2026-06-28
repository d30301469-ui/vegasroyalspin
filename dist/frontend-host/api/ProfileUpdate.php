<?php

/**
 * POST /api/v2/profile_update.php — profil güncelleme, Bearer JWT (zarf + data.errors).
 */
final class ApiProfileUpdate
{
    /**
     * @param array<string, mixed> $body JSON gövde (current_password zorunlu; diğer alanlar API şemasına göre)
     * @return array<string, mixed>|null
     */
    public static function submitEnvelope(string $memberJwt, array $body, int $timeout = 25): ?array
    {
        return ApiMemberApi::relayPostWithMemberJwt(
            MemberApiPaths::PROFILE_UPDATE,
            $memberJwt,
            $body,
            $timeout
        );
    }

    /**
     * data.errors alan haritasından okunabilir tek mesaj.
     *
     * @param array<string, mixed> $data Zarfın data nesnesi
     */
    public static function concatFieldErrors(array $data): string
    {
        $errors = $data['errors'] ?? null;
        if (!is_array($errors) || $errors === []) {
            return '';
        }
        $parts = [];
        foreach ($errors as $msg) {
            if (is_array($msg)) {
                foreach ($msg as $m) {
                    $parts[] = trim((string) $m);
                }
            } else {
                $parts[] = trim((string) $msg);
            }
        }

        return trim(implode(' ', array_filter($parts)));
    }
}
