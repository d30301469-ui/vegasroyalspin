<?php

declare(strict_types=1);

/**
 * Telegram bot webhook + Mini App auth (initData → Member JWT).
 */

$normalizedTelegramRoute = strtolower(trim((string) $route, '/'));
$isTelegramRoute = in_array($normalizedTelegramRoute, [
    'telegram/webhook',
    'telegram/webhook.php',
    'auth/telegram',
    'auth/telegram.php',
    'telegram/status',
    'telegram/status.php',
], true);

if ($isTelegramRoute) {
    admin_require_project_file('services/TelegramBotService.php');
    admin_require_project_file('services/TelegramAuthService.php');
}

// POST telegram/webhook — BotFather updates (no JWT / no CSRF)
if ($method === 'POST' && in_array($normalizedTelegramRoute, ['telegram/webhook', 'telegram/webhook.php'], true)) {
    $configuredSecret = TelegramBotService::webhookSecret();
    $isProduction = strtolower((string) (getenv('APP_ENV') ?: (defined('APP_ENV') ? APP_ENV : 'production'))) === 'production';
    if ($configuredSecret === '' && $isProduction) {
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'TELEGRAM_WEBHOOK_SECRET production ortamında zorunludur.',
        ]);
    }
    if ($configuredSecret !== '') {
        $provided = trim((string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''));
        if ($provided === '' || !hash_equals($configuredSecret, $provided)) {
            $memberEnvelope(401, [
                'success' => false,
                'code' => 401,
                'message' => 'Geçersiz webhook secret.',
            ]);
        }
    }

    if (!TelegramBotService::isConfigured()) {
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'Telegram bot yapılandırılmamış.',
        ]);
    }

    $update = is_array($payload['body'] ?? null) ? $payload['body'] : [];
    if ($update === [] && is_array($payload) && isset($payload['update_id'])) {
        $update = $payload;
    }

    try {
        $message = is_array($update['message'] ?? null) ? $update['message'] : null;
        $callback = is_array($update['callback_query'] ?? null) ? $update['callback_query'] : null;

        if ($message !== null) {
            $chatId = $message['chat']['id'] ?? null;
            $text = trim((string) ($message['text'] ?? ''));
            // Her mesaj / komut yalnızca uygulamayı açar.
            if ($chatId !== null) {
                TelegramBotService::sendWelcomePack($chatId);
            }
        } elseif ($callback !== null) {
            $chatId = $callback['message']['chat']['id'] ?? $callback['from']['id'] ?? null;
            $cbId = $callback['id'] ?? null;
            if ($cbId !== null) {
                try {
                    TelegramBotService::api('answerCallbackQuery', ['callback_query_id' => $cbId]);
                } catch (Throwable) {
                }
            }
            if ($chatId !== null) {
                TelegramBotService::sendWelcomePack($chatId);
            }
        }
    } catch (Throwable $e) {
        error_log('[telegram/webhook] ' . $e->getMessage());
    }

    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'ok',
        'data' => ['handled' => true],
    ]);
}

// POST auth/telegram — Mini App initData → JWT
if ($method === 'POST' && in_array($normalizedTelegramRoute, ['auth/telegram', 'auth/telegram.php'], true)) {
    if (!TelegramBotService::isConfigured()) {
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'Telegram bot yapılandırılmamış.',
        ]);
    }

    $input = $memberInput($payload);
    $initData = trim((string) ($input['init_data'] ?? $input['initData'] ?? ''));
    if ($initData === '') {
        $memberEnvelope(422, [
            'success' => false,
            'code' => 422,
            'message' => 'init_data zorunludur.',
        ]);
    }

    $validated = TelegramAuthService::validateInitData($initData);
    if (empty($validated['ok'])) {
        $memberEnvelope(401, [
            'success' => false,
            'code' => 401,
            'message' => (string) ($validated['error'] ?? 'Telegram doğrulaması başarısız.'),
        ]);
    }

    $pdo = AdminDatabase::pdo();
    try {
        $result = TelegramAuthService::findOrCreateMember($pdo, $validated['user']);
    } catch (Throwable $e) {
        error_log('[auth/telegram] ' . $e->getMessage());
        $memberEnvelope(500, [
            'success' => false,
            'code' => 500,
            'message' => 'Telegram hesabı bağlanamadı.',
        ]);
    }

    $user = $result['user'];
    if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
        $_SESSION['username'] = (string) ($user['username'] ?? '');
        $_SESSION['email'] = (string) ($user['email'] ?? '');
    }

    $jwt = '';
    try {
        $jwt = $memberJwtIssue($pdo, $user);
        if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
            $_SESSION['member_jwt'] = $jwt;
            if (function_exists('frontend_set_member_restore_cookie')) {
                frontend_set_member_restore_cookie($jwt);
            }
        }
    } catch (Throwable $e) {
        error_log('[auth/telegram] jwt: ' . $e->getMessage());
        $memberEnvelope(500, [
            'success' => false,
            'code' => 500,
            'message' => 'Oturum anahtarı üretilemedi.',
        ]);
    }

    try {
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) ($user['id'] ?? 0)]);
    } catch (Throwable) {
    }

    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => !empty($result['created']) ? 'Telegram hesabı oluşturuldu.' : 'Telegram ile giriş başarılı.',
        'data' => [
            'token' => $jwt,
            'jwt' => $jwt,
            'created' => !empty($result['created']),
            'user' => [
                'id' => (int) ($user['id'] ?? 0),
                'username' => (string) ($user['username'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'name' => (string) ($user['name'] ?? ''),
                'surname' => (string) ($user['surname'] ?? ''),
                'balance' => (float) ($user['balance'] ?? 0),
                'bonus_balance' => (float) ($user['bonus_balance'] ?? 0),
            ],
            'telegram' => [
                'id' => (int) ($validated['user']['id'] ?? 0),
                'username' => (string) ($validated['user']['username'] ?? ''),
            ],
        ],
    ]);
}

// GET telegram/status — ops health (no secrets)
if ($method === 'GET' && in_array($normalizedTelegramRoute, ['telegram/status', 'telegram/status.php'], true)) {
    $configured = TelegramBotService::isConfigured();
    $info = null;
    if ($configured) {
        try {
            $me = TelegramBotService::getMe();
            $info = [
                'ok' => !empty($me['ok']),
                'username' => (string) ($me['result']['username'] ?? TelegramBotService::username()),
                'id' => (int) ($me['result']['id'] ?? 0),
            ];
        } catch (Throwable $e) {
            $info = ['ok' => false, 'error' => $e->getMessage()];
        }
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Telegram durum',
        'data' => [
            'configured' => $configured,
            'miniapp_url' => TelegramBotService::miniAppUrl(),
            'bot' => $info,
            'webhook_secret_set' => TelegramBotService::webhookSecret() !== '',
        ],
    ]);
}
