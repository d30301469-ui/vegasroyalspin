<?php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

require_once BASE_PATH . '/config/app.php';
require_once SERVICE_PATH . '/BackendApiClient.php';

/**
 * Drakon oyun başlatma – giriş noktası (redirect).
 */
class ApiDrakonGameController
{
    private static function logResponse(string $message, $data = null): void
    {
        $logDir = BASE_PATH . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/drakon_api_responses.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}";
        if ($data !== null) {
            $logMessage .= ' - Data: ' . (is_array($data) || is_object($data)
                ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : (string) $data);
        }
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function launch(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__ . '/../../config/frontend_session.php';
            metropol_frontend_session_start();
        }

        if (!isset($_SESSION['username']) || $_SESSION['username'] === '') {
            if (isset($_GET['reloaded']) && $_GET['reloaded'] === '1') {
                self::logResponse('User not logged in, redirecting to index');
                header('Location: /?error=not_logged_in');
                exit;
            }
            $currentUrl = $_SERVER['REQUEST_URI'];
            $separator = (strpos($currentUrl, '?') === false) ? '?' : '&';
            self::logResponse('Session not found, refreshing page', ['current_url' => $currentUrl]);
            echo "<script>setTimeout(function(){window.location.href = '" . htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') . $separator . "reloaded=1';}, 1000);</script>";
            exit;
        }

        $game_id = $_GET['gameid'] ?? null;
        if (!$game_id) {
            self::logResponse('Game ID not provided', ['get_params' => $_GET]);
            echo 'Error: Game ID not provided.';
            exit;
        }

        self::logResponse('Game launch process started', ['game_id' => $game_id]);

        if (!frontend_database_allowed()) {
            $this->launchViaBackendApi((string) $game_id);
            return;
        }

        require_once BASE_PATH . '/admin/app/bootstrap.php';

        $username = $_SESSION['username'];
        $j        = BackendApiClient::request('GET', BackendApiClient::SVC_MAIN, '/users/by-username', ['username' => $username]);
        $row      = BackendApiClient::unwrap($j);
        $user_id  = $row['id'] ?? $j['id'] ?? null;

        if ($user_id === null) {
            self::logResponse('User not found via API');
            echo 'Error: User not found.';
            exit;
        }
        $user_id = (int) $user_id;

        require_once SERVICE_PATH . '/DrakonService.php';

        try {
            $result = DrakonService::launch(AdminDatabase::pdo(), [
                'id' => $user_id,
                'username' => $username,
            ], [
                'game_id' => $game_id,
                'mode' => 'real',
                'lang' => 'tr',
            ]);
            $this->redirectFromLaunchResult($result);
        } catch (\Throwable $e) {
            self::logResponse('Drakon launch exception', ['message' => $e->getMessage()]);
            echo 'Error: Oyun başlatılamadı.';
        }
    }

    private function launchViaBackendApi(string $gameId): void
    {
        $jwt = trim((string) ($_SESSION['member_jwt'] ?? ''));
        if ($jwt === '') {
            self::logResponse('Member JWT missing for backend game launch');
            echo 'Error: Oturum doğrulanamadı. Lütfen tekrar giriş yapın.';
            exit;
        }

        $result = BackendApiClient::requestWithMemberBearer(
            'POST',
            BackendApiClient::SVC_GAMES,
            'game_launch.php',
            $jwt,
            [],
            [
                'game_id' => $gameId,
                'gameid' => $gameId,
                'mode' => 'real',
                'lang' => 'tr',
            ]
        );

        if ($result === null) {
            self::logResponse('Backend game_launch.php request failed');
            echo 'Error: Oyun başlatılamadı.';
            exit;
        }

        $this->redirectFromLaunchResult($result);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function redirectFromLaunchResult(array $result): void
    {
        $data = BackendApiClient::unwrap($result);
        $gameUrl = (string) ($data['game_url'] ?? $data['launch_url'] ?? $result['game_url'] ?? $result['launch_url'] ?? '');
        if (!empty($result['success']) && $gameUrl !== '') {
            self::logResponse('Redirecting to game URL', ['session_id' => (string) ($data['session_id'] ?? '')]);
            header('Location: ' . $gameUrl);
            exit;
        }

        self::logResponse('Game launch failed', [
            'code' => (int) ($result['code'] ?? 502),
            'message' => (string) ($result['message'] ?? 'Launch failed'),
        ]);
        echo 'Error: Oyun başlatılamadı.';
    }
}
