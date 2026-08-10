<?php

declare(strict_types=1);

/**
 * Telegram webhook kaydı (sunucuda çalıştır).
 *
 *   php scripts/telegram-set-webhook.php
 *
 * Gereken .env:
 *   TELEGRAM_BOT_TOKEN=
 *   TELEGRAM_WEBHOOK_SECRET=  (önerilir)
 *   TELEGRAM_MINIAPP_URL=https://m.vegasroyalspin.com/tg
 *
 * Webhook URL varsayılanı: https://vegasroyalspin.com/api/v2/telegram/webhook
 */

$root = dirname(__DIR__);
chdir($root);

if (is_readable($root . '/.env')) {
    foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if ($k !== '' && getenv($k) === false) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

require_once $root . '/services/TelegramBotService.php';

if (!TelegramBotService::isConfigured()) {
    fwrite(STDERR, "TELEGRAM_BOT_TOKEN eksik veya placeholder.\n");
    exit(1);
}

$webhookUrl = trim((string) (getenv('TELEGRAM_WEBHOOK_URL') ?: ''));
if ($webhookUrl === '') {
    $webhookUrl = 'https://vegasroyalspin.com/api/v2/telegram/webhook';
}

echo "Bot: @" . TelegramBotService::username() . PHP_EOL;
echo "Mini App: " . TelegramBotService::miniAppUrl() . PHP_EOL;
echo "Webhook: " . $webhookUrl . PHP_EOL;

try {
    $me = TelegramBotService::getMe();
    echo 'getMe: ' . json_encode($me, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    $set = TelegramBotService::setWebhook($webhookUrl);
    echo 'setWebhook: ' . json_encode($set, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    $info = TelegramBotService::getWebhookInfo();
    echo 'getWebhookInfo: ' . json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "OK\n";
echo "BotFather: /setdomain → m.vegasroyalspin.com\n";
echo "BotFather: /setmenubutton veya Mini App butonu için web_app URL = TELEGRAM_MINIAPP_URL\n";
