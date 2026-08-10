<?php

declare(strict_types=1);

/**
 * Telegram bot marka / komut / menü özelleştirmesi.
 *
 *   php scripts/telegram-customize-bot.php
 *
 * Görünen ad sabit: Vegasroyalspin
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
            $_SERVER[$k] = $v;
        }
    }
}

require_once $root . '/services/TelegramBotService.php';

if (!TelegramBotService::isConfigured()) {
    fwrite(STDERR, "TELEGRAM_BOT_TOKEN eksik.\n");
    exit(1);
}

echo 'Brand: ' . TelegramBotService::brandName() . PHP_EOL;
echo 'Mini App: ' . TelegramBotService::miniAppUrl() . PHP_EOL;

try {
    $results = TelegramBotService::applyBranding();
    foreach ($results as $method => $payload) {
        $ok = !empty($payload['ok']) ? 'OK' : 'FAIL';
        $extra = $payload['description'] ?? ($payload['error_code'] ?? '');
        echo $method . ': ' . $ok . ($extra !== '' ? ' (' . $extra . ')' : '') . PHP_EOL;
    }
    $me = TelegramBotService::getMe();
    echo 'getMe name/username: '
        . ($me['result']['first_name'] ?? '?')
        . ' / @'
        . ($me['result']['username'] ?? '')
        . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "OK\n";
echo "BotFather not: /setuserpic ile logo ekleyebilirsin. Username değişmez (@vegasroyalspin_bot).\n";
