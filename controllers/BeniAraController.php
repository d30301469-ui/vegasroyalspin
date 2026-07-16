<?php

require_once SERVICE_PATH . '/ProfileApiHelper.php';

class BeniAraController extends Controller
{
    private const MAX_LENGTH = [
        'ad'            => 255,
        'kullanici_adi' => 255,
        'telefon'       => 64,
        'neden'         => 4000,
        'mesaj'         => 8000,
    ];

    public function index(): void
    {
        $gonderildi             = false;
        $hata                   = '';
        $callMeSuccessMessage   = '';

        $profilePrefill        = $this->loadCallMeProfilePrefill();
        $callMeProfileReadonly = [
            'ad'            => $profilePrefill['ad'] !== '',
            'kullanici_adi' => $profilePrefill['kullanici_adi'] !== '',
            'telefon'       => $profilePrefill['telefon'] !== '',
        ];

        $formData = $this->getDefaultFormData();
        foreach (['ad', 'kullanici_adi', 'telefon'] as $k) {
            if ($profilePrefill[$k] !== '') {
                $formData[$k] = $profilePrefill[$k];
            }
        }

        $memberJwt = isset($_SESSION['member_jwt']) ? (string) $_SESSION['member_jwt'] : null;
        if ($memberJwt === '') {
            $memberJwt = null;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCsrf()) {
                $hata     = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
                $formData = $this->applyProfilePrefillToPostedCallMeData($this->collectPostData(), $profilePrefill);
            } else {
                $formData = $this->applyProfilePrefillToPostedCallMeData($this->collectPostData(), $profilePrefill);
                $hata     = $this->validateForm($formData);
                if ($hata === '') {
                    $messageParts = [$formData['neden']];
                    if ($formData['mesaj'] !== '') {
                        $messageParts[] = $formData['mesaj'];
                    }

                    $body = [
                        'full_name' => $formData['ad'],
                        'name'      => $formData['ad'],
                        'phone'     => $formData['telefon'],
                        'message'   => implode("\n", array_filter($messageParts, static fn ($part) => $part !== '')),
                    ];
                    if ($formData['kullanici_adi'] !== '') {
                        $body['username'] = $formData['kullanici_adi'];
                    }

                    $res = ApiCallMeRequest::submit($body, $memberJwt);
                    if ($res === null) {
                        $hata = 'Şu anda talebiniz iletilemedi. Lütfen daha sonra tekrar deneyin.';
                    } else {
                        $code    = (int) ($res['code'] ?? 0);
                        $success = !empty($res['success']);
                        if ($success && ($code === 201 || $code === 200)) {
                            $gonderildi           = true;
                            $d                    = BackendApiClient::unwrap($res);
                            $callMeSuccessMessage = trim((string) ($d['confirmation_message'] ?? ''));
                            if ($callMeSuccessMessage === '') {
                                $callMeSuccessMessage = (string) ($res['message'] ?? 'Talebiniz alındı. En kısa sürede sizinle iletişime geçeceğiz.');
                            }
                        } elseif ($code === 400 || $code === 422) {
                            $hata = $this->formatApiValidationErrors($res);
                        } elseif ($code === 429 || ($res['error'] ?? '') === 'RATE_LIMITED') {
                            $hata = (string) ($res['message'] ?? 'Çok fazla talep gönderildi. Lütfen bir süre sonra tekrar deneyin.');
                        } elseif ($code === 403 || ($res['error'] ?? '') === 'USER_BANNED') {
                            $hata = (string) ($res['message'] ?? 'Kullanıcı banlanmıştır');
                        } elseif ($code === 409 || ($res['error'] ?? '') === 'CALL_ME_ALREADY_SUBMITTED') {
                            $hata = (string) ($res['message'] ?? 'Bu hesap veya bağlantı için zaten bir geri arama talebi kayıtlı.');
                        } else {
                            $hata = trim((string) ($res['message'] ?? ''));
                            if ($hata === '') {
                                $hata = 'Talebiniz gönderilemedi.';
                            }
                        }
                    }
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->wantsJsonResponse()) {
            header('Content-Type: application/json; charset=UTF-8');
            if ($gonderildi) {
                $msg = $callMeSuccessMessage !== '' ? $callMeSuccessMessage : 'Talebiniz alındı. En kısa sürede sizinle iletişime geçeceğiz.';
                echo json_encode(['ok' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
            } else {
                $msg = $hata !== '' ? $hata : 'Talebiniz gönderilemedi.';
                echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
            }

            return;
        }

        $this->view('pages/beni-ara', compact(
            'gonderildi',
            'hata',
            'formData',
            'callMeSuccessMessage',
            'callMeProfileReadonly'
        ));
    }

    /**
     * Giriş yapmış üye: profil API (JWT) veya MAIN profile; misafir: boş.
     *
     * @return array{ad: string, kullanici_adi: string, telefon: string}
     */
    private function loadCallMeProfilePrefill(): array
    {
        $empty = ['ad' => '', 'kullanici_adi' => '', 'telefon' => ''];
        if (empty($_SESSION['loggedin'])) {
            return $empty;
        }

        $profileV1 = null;
        $jwt       = isset($_SESSION['member_jwt']) ? trim((string) $_SESSION['member_jwt']) : '';
        if ($jwt !== '') {
            $env = ApiProfileDetail::fetchEnvelope($jwt);
            if ($env !== null && ApiEnvelope::isOk($env)) {
                $raw = $env['data']['profile'] ?? null;
                if (is_array($raw)) {
                    $profileV1 = $raw;
                }
            }
        }

        if ($profileV1 !== null) {
            $p = ApiProfileDetail::formPrefillFromProfile($profileV1);
            $ad = trim(trim((string) $p['first_name']) . ' ' . trim((string) $p['surname']));
            $username = $p['username'] !== ''
                ? $p['username']
                : trim((string) ($_SESSION['username'] ?? ''));

            return [
                'ad'            => $ad,
                'kullanici_adi' => $username,
                'telefon'       => $p['phone'],
            ];
        }

        $username = trim((string) ($_SESSION['username'] ?? ''));
        if ($username === '') {
            return $empty;
        }

        $user = ProfileApiHelper::profileByUsername($username);
        if ($user === []) {
            return [
                'ad'            => '',
                'kullanici_adi' => $username,
                'telefon'       => '',
            ];
        }

        $first = trim((string) ($user['first_name'] ?? ''));
        $last  = trim((string) ($user['surname'] ?? ''));

        return [
            'ad'            => trim($first . ' ' . $last),
            'kullanici_adi' => $username,
            'telefon'       => trim((string) ($user['phone'] ?? '')),
        ];
    }

    /**
     * Profilde dolu olan alanları POST üzerine yazar; eksikse kullanıcı girişi kullanılır.
     *
     * @param array{ad: string, kullanici_adi: string, telefon: string, neden: string, mesaj: string} $post
     * @param array{ad: string, kullanici_adi: string, telefon: string}                               $prefill
     *
     * @return array{ad: string, kullanici_adi: string, telefon: string, neden: string, mesaj: string}
     */
    private function applyProfilePrefillToPostedCallMeData(array $post, array $prefill): array
    {
        foreach (['ad', 'kullanici_adi', 'telefon'] as $k) {
            if (($prefill[$k] ?? '') !== '') {
                $post[$k] = $prefill[$k];
            }
        }

        return $post;
    }

    private function wantsJsonResponse(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        return str_contains($accept, 'application/json');
    }

    private function validateCsrf(): bool
    {
        return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
            && hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token']);
    }

    private function getDefaultFormData(): array
    {
        return [
            'ad'            => '',
            'kullanici_adi' => '',
            'telefon'       => '',
            'neden'         => '',
            'mesaj'         => '',
        ];
    }

    private function collectPostData(): array
    {
        $data = [];
        foreach (array_keys(self::MAX_LENGTH) as $key) {
            $raw          = trim((string) ($_POST[$key] ?? ''));
            $data[$key] = mb_substr($raw, 0, self::MAX_LENGTH[$key]);
        }

        return $data;
    }

    private function validateForm(array $data): string
    {
        if ($data['ad'] === '') {
            return 'Adınızı giriniz.';
        }
        if ($data['telefon'] === '') {
            return 'Telefon numaranızı giriniz.';
        }
        if ($data['neden'] === '') {
            return 'Neden aranmak istediğinizi belirtiniz.';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $res
     */
    private function formatApiValidationErrors(array $res): string
    {
        $details = $res['details'] ?? null;
        if (!is_array($details)) {
            return (string) ($res['message'] ?? 'Doğrulama hatası');
        }
        $errors = $details['errors'] ?? null;
        if (!is_array($errors) || $errors === []) {
            return (string) ($res['message'] ?? 'Doğrulama hatası');
        }
        $parts = [];
        foreach ($errors as $msgs) {
            if (!is_array($msgs)) {
                continue;
            }
            foreach ($msgs as $m) {
                $parts[] = (string) $m;
            }
        }

        return $parts !== [] ? implode(' ', $parts) : (string) ($res['message'] ?? 'Doğrulama hatası');
    }
}
