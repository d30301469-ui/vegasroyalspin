<?php

/**
 * GET /api/v2/profile_detail.php — üye profil özeti, Bearer JWT (api.md).
 */
final class ApiProfileDetail
{
    /**
     * @return array<string, mixed>|null
     */
    public static function fetchEnvelope(string $memberJwt): ?array
    {
        return ApiMemberApi::relayGetWithMemberJwt(
            MemberApiPaths::PROFILE_DETAIL,
            $memberJwt,
            [],
            20
        );
    }

    /**
     * Sunucu profil nesnesini kişisel detaylar formu alanlarına çevirir (şema genişleyebilir).
     *
     * @param array<string, mixed> $p
     * @return array{
     *   username: string,
     *   first_name: string,
     *   surname: string,
     *   dob: string,
     *   gender: string,
     *   phone: string,
     *   country: string,
     *   city: string,
     *   address: string,
     *   email: string,
     *   tc: string,
     *   status: string
     * }
     */
    public static function formPrefillFromProfile(array $p): array
    {
        $first = trim((string) ($p['first_name'] ?? ''));
        $last  = trim((string) ($p['surname'] ?? ''));
        $name  = trim((string) ($p['name'] ?? ''));
        if ($first === '' && $last === '' && $name !== '') {
            $parts = preg_split('/\s+/u', $name, 2, PREG_SPLIT_NO_EMPTY);
            $first = (string) ($parts[0] ?? '');
            $last  = (string) ($parts[1] ?? '');
        }

        $genderRaw = strtolower(trim((string) ($p['gender'] ?? '')));
        $genderForm = match ($genderRaw) {
            'male', 'm', 'erkek' => 'Erkek',
            'female', 'f', 'kadın', 'kadin' => 'Kadın',
            'other', 'diğer', 'diger' => 'Diğer',
            default => $genderRaw !== '' ? 'Diğer' : '',
        };

        $countryRaw = trim((string) ($p['country'] ?? ''));
        $countryDisplay = self::countryLabel($countryRaw);

        $tc = $p['tc'] ?? null;
        $tcStr = ($tc === null || $tc === '') ? '' : (string) $tc;

        $status = strtolower(trim((string) ($p['status'] ?? '')));

        return [
            'username'   => trim((string) ($p['username'] ?? '')),
            'first_name' => $first,
            'surname'    => $last,
            'dob'        => trim((string) ($p['birth_date'] ?? $p['dob'] ?? '')),
            'gender'     => $genderForm,
            'phone'      => trim((string) ($p['phone'] ?? '')),
            'country'    => $countryDisplay,
            'city'       => trim((string) ($p['city'] ?? '')),
            'address'    => trim((string) ($p['address'] ?? '')),
            'email'      => trim((string) ($p['email'] ?? '')),
            'tc'         => $tcStr,
            'status'     => $status,
        ];
    }

    private static function countryLabel(string $codeOrName): string
    {
        $c = strtoupper($codeOrName);
        if ($c === 'TR' || $c === 'TUR' || $c === 'TURKEY') {
            return 'Türkiye';
        }

        return $codeOrName;
    }
}
