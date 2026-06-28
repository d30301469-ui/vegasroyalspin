<?php

require_once __DIR__ . '/TurkishNationalId.php';

/**
 * Kayıt formu / JSON gövdesi: alan çıkarma, doğrulama, backend register.php gövdesi (api.md).
 */
final class MemberRegisterPayload
{
    /**
     * @param array<string, mixed> $row Ham input (POST veya JSON)
     * @return array<string, mixed>
     */
    public static function extractFields(array $row): array
    {
        return [
            'username'              => trim((string) ($row['username'] ?? '')),
            'email'                 => trim((string) ($row['email'] ?? '')),
            'password'              => (string) ($row['password'] ?? ''),
            'password_confirmation' => (string) ($row['password_confirmation'] ?? $row['confirm_password'] ?? ''),
            'first_name'            => trim((string) ($row['first_name'] ?? $row['firstName'] ?? $row['name'] ?? '')),
            'surname'               => trim((string) ($row['surname'] ?? '')),
            'country'               => strtoupper(trim((string) ($row['country'] ?? ''))),
            'city'                  => trim((string) ($row['city'] ?? '')),
            'birth_date'            => trim((string) ($row['birth_date'] ?? $row['dob'] ?? '')),
            'gender_raw'            => trim((string) ($row['gender'] ?? '')),
            'phone'                 => trim((string) ($row['phone'] ?? '')),
            'phone_country_code'    => trim((string) ($row['phone_country_code'] ?? '')),
            'tc'                    => preg_replace('/\D/', '', (string) ($row['tc'] ?? $row['tc_kimlik_no'] ?? $row['tcKimlik'] ?? $row['identity_number'] ?? '')),
            'address'               => trim((string) ($row['address'] ?? '')),
            'terms_accepted'        => $row['terms_accepted'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed> extractFields + phone_norm, gender_api
     */
    public static function prepare(array $row): array
    {
        $f = self::extractFields($row);

        return $f + [
            'phone_norm'  => self::normalizePhoneDigits($f['phone'], $f['phone_country_code']),
            'gender_api'  => self::mapGenderToApi($f['gender_raw']),
        ];
    }

    /**
     * @param array<string, mixed> $prepared self::prepare() çıktısı
     * @return array<string, string>
     */
    public static function collectFieldErrors(array $prepared, bool $requireTerms): array
    {
        $errors = [];

        if ($prepared['username'] === '') {
            $errors['username'] = 'Kullanıcı adı gerekli.';
        }
        if ($prepared['email'] === '' || !filter_var($prepared['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Geçerli bir e-posta girin.';
        }
        if ($prepared['password'] === '') {
            $errors['password'] = 'Şifre gerekli.';
        }
        if ($prepared['password_confirmation'] !== '' && $prepared['password'] !== $prepared['password_confirmation']) {
            $errors['password_confirmation'] = 'Şifreler eşleşmiyor.';
        }
        if ($prepared['first_name'] === '') {
            $errors['first_name'] = 'Ad gerekli.';
        }
        if ($prepared['surname'] === '') {
            $errors['surname'] = 'Soyad gerekli.';
        }
        if ($prepared['country'] === '') {
            $errors['country'] = 'Ülke kodu gerekli.';
        }
        if ($prepared['city'] === '') {
            $errors['city'] = 'Şehir gerekli.';
        }
        if ($prepared['gender_raw'] === '') {
            $errors['gender'] = 'Cinsiyet gerekli.';
        }
        if ($prepared['birth_date'] === '') {
            $errors['birth_date'] = 'Doğum tarihi gerekli.';
        } else {
            $bdErr = self::birthDateValidationError($prepared['birth_date']);
            if ($bdErr !== null) {
                $errors['birth_date'] = $bdErr;
            }
        }

        if (strlen((string) $prepared['phone_norm']) < 10) {
            $errors['phone'] = 'Telefon en az 10 rakam olmalıdır.';
        }

        if ($prepared['gender_api'] === '') {
            $errors['gender'] = 'Cinsiyet geçersiz (male/female/other veya Erkek/Kadın/Diğer).';
        }

        if ($prepared['country'] === 'TR') {
            $tc = (string) $prepared['tc'];
            if ($tc === '' || strlen($tc) !== 11) {
                $errors['tc'] = 'Türkiye için 11 haneli T.C. kimlik numarası gerekli.';
            } elseif (!TurkishNationalId::isValid($tc)) {
                $errors['tc'] = 'T.C. kimlik numarası algoritmaya uygun değil.';
            }
        }

        if ($requireTerms && empty($prepared['terms_accepted'])) {
            $errors['terms_accepted'] = 'Devam etmek için şartları kabul etmelisiniz.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $prepared self::prepare() çıktısı
     * @return array<string, mixed>
     */
    public static function buildBackendBody(array $prepared, string $referralCode, string $bonusCode): array
    {
        $body = [
            'username'    => $prepared['username'],
            'email'       => $prepared['email'],
            'password'    => $prepared['password'],
            'first_name'  => $prepared['first_name'],
            'surname'     => $prepared['surname'],
            'phone'       => $prepared['phone_norm'],
            'gender'      => $prepared['gender_api'],
            'birth_date'  => $prepared['birth_date'],
            'country'     => $prepared['country'],
            'city'        => $prepared['city'],
            'address'     => $prepared['address'],
        ];
        if ($prepared['password_confirmation'] !== '') {
            $body['password_confirmation'] = $prepared['password_confirmation'];
        }
        if ($prepared['country'] === 'TR' && $prepared['tc'] !== '') {
            $body['tc'] = $prepared['tc'];
        }
        if ($referralCode !== '') {
            $body['referral_code'] = $referralCode;
        }
        if ($bonusCode !== '') {
            $body['bonus_code'] = $bonusCode;
        }

        return $body;
    }

    private static function birthDateValidationError(string $ymd): ?string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
        if (!$dt || $dt->format('Y-m-d') !== $ymd) {
            return 'Geçerli bir doğum tarihi girin (YYYY-MM-DD).';
        }
        $today = new DateTimeImmutable('today');
        if ($dt >= $today) {
            return 'Doğum tarihi geçmiş bir tarih olmalıdır.';
        }
        if ($dt->diff($today)->y < 18) {
            return 'Kayıt için en az 18 yaşında olmalısınız.';
        }

        return null;
    }

    private static function normalizePhoneDigits(string $phone, string $countryCodeDigits): string
    {
        $d   = (string) preg_replace('/\D/', '', $phone);
        $cc  = (string) preg_replace('/\D/', '', $countryCodeDigits);
        $ccLen = strlen($cc);
        if ($ccLen > 0 && strlen($d) > $ccLen && substr($d, 0, $ccLen) === $cc) {
            $d = substr($d, $ccLen);
        }

        return ltrim($d, '0');
    }

    private static function mapGenderToApi(string $g): string
    {
        $g = trim(mb_strtolower($g, 'UTF-8'));
        $map = [
            'erkek' => 'male',
            'kadın' => 'female',
            'kadin' => 'female',
            'diğer' => 'other',
            'diger' => 'other',
            'male'  => 'male',
            'female'=> 'female',
            'other' => 'other',
            'm'     => 'male',
            'f'     => 'female',
            'o'     => 'other',
        ];

        return $map[$g] ?? '';
    }
}
