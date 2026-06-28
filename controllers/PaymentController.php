<?php

require_once SERVICE_PATH . '/BackendApiClient.php';

/**
 * Ödeme işlemleri – sunum katmanı (controller).
 */
class PaymentController extends Controller
{
    public function megapayzDeposit(): void
    {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Giriş yapmanız gerekiyor.';
            return;
        }

        $username = $_SESSION['username'] ?? '';
        if ($username === '') {
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Oturum bilgisi bulunamadı.';
            return;
        }

        require_once SERVICE_PATH . '/ProfileApiHelper.php';
        $user = ProfileApiHelper::profileByUsername($username);
        if ($user === [] || empty($user['id'])) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Kullanıcı bulunamadı.';
            return;
        }

        $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 100.0;
        if ($amount <= 0) {
            $amount = 100.0;
        }

        $method = isset($_POST['method']) ? trim((string) $_POST['method']) : '';
        $memberRow = [
            'id'         => (int) $user['id'],
            'username'   => $username,
            'first_name' => $user['first_name'] ?? '',
            'surname'    => $user['surname'] ?? '',
            'name'       => $user['name'] ?? '',
            'email'      => $user['email'] ?? '',
        ];

        if (!function_exists('frontend_database_allowed') || !frontend_database_allowed()) {
            $result = $this->createDepositViaApi($method, $amount);
        } else {
            require_once SERVICE_PATH . '/MegaPayzService.php';
            if (!defined('ADMIN_APP_PATH')) {
                define('ADMIN_APP_PATH', BASE_PATH . '/admin/app');
            }
            if (!class_exists('AdminDatabase', false)) {
                require_once ADMIN_APP_PATH . '/Core/AdminDatabase.php';
            }

            try {
                $result = MegaPayzService::createDeposit(AdminDatabase::pdo(), $memberRow, $method, $amount);
            } catch (Throwable $e) {
                header('Content-Type: text/plain; charset=UTF-8');
                echo 'Ödeme servisi şu anda kullanılamıyor.';
                return;
            }
        }

        $redirect = (string) ($result['data']['redirect_url'] ?? $result['data']['payment_url'] ?? '');
        if (!empty($result['success']) && $redirect !== '') {
            header('Location: ' . $redirect);
            exit;
        }

        $code    = $result['code'] ?? 'Bilinmiyor';
        $message = $result['message'] ?? 'Hata oluştu.';
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Hata ({$code}): {$message}";
    }

    /**
     * @return array<string, mixed>
     */
    private function createDepositViaApi(string $method, float $amount): array
    {
        $jwt = (string) ($_SESSION['member_jwt'] ?? '');
        if ($jwt === '') {
            return [
                'success' => false,
                'code' => 401,
                'message' => 'Oturum token bulunamadı. Lütfen tekrar giriş yapın.',
            ];
        }

        $response = BackendApiClient::requestWithMemberBearer(
            'POST',
            BackendApiClient::SVC_MAIN,
            '/deposit_payment.php',
            $jwt,
            [],
            [
                'amount' => $amount,
                'method' => $method,
                'payment_method' => $method,
            ]
        );

        if (!is_array($response)) {
            return [
                'success' => false,
                'code' => 503,
                'message' => 'Ödeme API yanıt vermedi.',
            ];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            return $response;
        }

        return [
            'success' => !empty($response['success']),
            'code' => (int) ($response['code'] ?? 422),
            'message' => (string) ($response['message'] ?? 'Hata oluştu.'),
            'data' => is_array($response['data'] ?? null) ? $response['data'] : [],
        ];
    }
}
