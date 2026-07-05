<?php

require_once SERVICE_PATH . '/BackendApiClient.php';

/**
 * Legacy /game/launch uyumlulugu. Gercek launch artik /api/v2/game-launch uzerinden BGaming ile yapilir.
 */
class GameController
{
    public function launch(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__ . '/../config/frontend_session.php';
            metropol_frontend_session_start();
        }

        $mode = strtolower(trim((string) ($_GET['mode'] ?? 'real')));
        $demo = isset($_GET['demo']) && ($_GET['demo'] === '1' || $_GET['demo'] === 'true');
        if ($demo || in_array($mode, ['fun', 'demo'], true)) {
            $mode = 'fun';
        }

        if ($mode === 'real' && !isset($_SESSION['username'])) {
            $_SESSION['toast_message'] = 'LÃ¼tfen hesabÄ±nÄ±za giriÅŸ yapÄ±nÄ±z!';
            $_SESSION['toast_type'] = 'warning';
            header('Location: /');
            exit;
        }

        $game_code = $_GET['game_id'] ?? $_GET['gameId'] ?? $_GET['gameid'] ?? $_GET['kod'] ?? $_GET['game'] ?? '';

        if (empty($game_code)) {
            header('Location: /slot');
            exit;
        }

        $query = http_build_query([
            'game_id' => (string) $game_code,
            'mode' => $mode === 'fun' ? 'fun' : 'real',
            'wallet' => (string) ($_GET['wallet'] ?? 'main'),
        ]);
        header('Location: /play?' . $query);
        exit;
    }

    private function getChannel(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $mobileAgents = ['Android', 'webOS', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone', 'Opera Mini', 'IEMobile', 'Mobile'];
        foreach ($mobileAgents as $agent) {
            if (stripos($ua, $agent) !== false) {
                return 'mobile';
            }
        }
        return 'desktop';
    }

    private function renderLaunchError(string $error_msg, int $error_code, string $game_name, string $vendor_name, string $channel): void
    {
        $messages = [
            5  => 'KullanÄ±cÄ± bulunamadÄ±. LÃ¼tfen tekrar giriÅŸ yapÄ±n.',
            8  => 'Yetersiz bakiye. LÃ¼tfen bakiye yÃ¼kleyin.',
            12 => 'GeÃ§ersiz oyun saÄŸlayÄ±cÄ±sÄ±.',
            13 => 'GeÃ§ersiz parametre.',
            14 => 'Network hatasÄ±. LÃ¼tfen tekrar deneyin.',
        ];
        $display_message = $messages[$error_code] ?? $error_msg;
        $device          = $channel === 'mobile' ? 'Mobile' : 'Desktop';
        $error_code      = $error_code;
        require VIEW_PATH . '/pages/game-launch-error.php';
        exit;
    }
}
