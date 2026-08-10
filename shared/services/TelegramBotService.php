<?php

declare(strict_types=1);

/**
 * Telegram Bot API istemcisi (sendMessage, setWebhook, getMe, branding).
 */
final class TelegramBotService
{
    public const BRAND_NAME = 'Vegasroyalspin';

    public static function brandName(): string
    {
        $name = self::env('TELEGRAM_BRAND_NAME', self::BRAND_NAME);

        return $name !== '' ? $name : self::BRAND_NAME;
    }

    public static function token(): string
    {
        return self::env('TELEGRAM_BOT_TOKEN');
    }

    public static function username(): string
    {
        return ltrim(self::env('TELEGRAM_BOT_USERNAME', 'vegasroyalspin_bot'), '@');
    }

    public static function miniAppUrl(): string
    {
        $url = self::env('TELEGRAM_MINIAPP_URL', 'https://m.vegasroyalspin.com/tg');

        return rtrim($url !== '' ? $url : 'https://m.vegasroyalspin.com/tg', '/');
    }

    public static function webhookSecret(): string
    {
        return self::env('TELEGRAM_WEBHOOK_SECRET');
    }

    public static function supportUrl(): string
    {
        if (defined('LIVE_SUPPORT_URL') && is_string(LIVE_SUPPORT_URL) && trim(LIVE_SUPPORT_URL) !== '') {
            return rtrim(trim(LIVE_SUPPORT_URL), '/') . '/';
        }
        $fromEnv = self::env('LIVE_SUPPORT_URL', 'https://direct.lc.chat/19301899/');

        return $fromEnv !== '' ? (rtrim($fromEnv, '/') . '/') : 'https://direct.lc.chat/19301899/';
    }

    /** Mini App deep-link (panel hash). */
    public static function appUrl(string $panel = ''): string
    {
        $base = self::miniAppUrl();
        $panel = ltrim(trim($panel), '#');
        if ($panel === '' || $panel === 'home') {
            return $base;
        }

        return $base . '#' . $panel;
    }

    /** putenv kapalı (aaPanel) sunucularda $_ENV/$_SERVER/constant fallback. */
    private static function env(string $key, string $default = ''): string
    {
        foreach ([
            getenv($key),
            $_ENV[$key] ?? null,
            $_SERVER[$key] ?? null,
            defined($key) ? constant($key) : null,
        ] as $candidate) {
            if ($candidate === false || $candidate === null) {
                continue;
            }
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }

    public static function isConfigured(): bool
    {
        $token = self::token();

        return $token !== '' && !str_contains(strtolower($token), 'change-me');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function api(string $method, array $payload = []): array
    {
        $token = self::token();
        if ($token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN tanımlı değil.');
        }

        $url = 'https://api.telegram.org/bot' . $token . '/' . ltrim($method, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Telegram API curl init failed.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($raw)) {
            throw new RuntimeException('Telegram API network error: ' . ($error !== '' ? $error : 'unknown'));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Telegram API invalid JSON (HTTP ' . $status . ').');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function sendMessage(int|string $chatId, string $text, array $extra = []): array
    {
        return self::api('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $extra));
    }

    /** @return array<string, mixed> */
    public static function mainReplyKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    [
                        'text' => '▶ Uygulamayı Aç',
                        'web_app' => ['url' => self::appUrl()],
                    ],
                ],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
            'input_field_placeholder' => self::brandName(),
        ];
    }

    /** @return list<list<array<string, mixed>>> */
    public static function mainInlineKeyboard(): array
    {
        return [
            [[
                'text' => '▶ Uygulamayı Aç',
                'web_app' => ['url' => self::appUrl()],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sendMiniAppOpen(int|string $chatId, string $text = '', string $panel = ''): array
    {
        $brand = self::brandName();
        $safe = htmlspecialchars($brand, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($text === '') {
            $text = "🎰 <b>{$safe}</b>\n"
                . "────────────────\n"
                . "Slot · Canlı Casino · Spor\n"
                . "Para yatır / çek — uygulama içinde\n\n"
                . "<i>Aşağıdaki butona basarak uygulamayı açın.</i>";
        }

        return self::sendMessage($chatId, $text, [
            'reply_markup' => [
                'inline_keyboard' => self::mainInlineKeyboard(),
                // Reply keyboard aynı mesajda gönderilemez; welcome pack ayırır.
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sendWelcomePack(int|string $chatId): array
    {
        $open = self::sendMiniAppOpen($chatId);
        try {
            // Kalıcı alt buton: metin açıkça "buton / aksiyon"
            self::sendMessage($chatId, '⬇️ Hızlı erişim', [
                'reply_markup' => self::mainReplyKeyboard(),
            ]);
        } catch (Throwable) {
        }

        return $open;
    }

    /**
     * BotFather profil / menü / komut özelleştirmesi (API).
     * Tek komut: /start — Vegasroyalspin → yalnızca uygulamayı açar.
     *
     * @return array<string, mixed>
     */
    public static function applyBranding(): array
    {
        $brand = self::brandName();
        $results = [];

        $results['setMyName'] = self::api('setMyName', [
            'name' => $brand,
        ]);

        $results['setMyDescription'] = self::api('setMyDescription', [
            'description' => $brand . ' — Slot, canlı casino ve spor. Uygulamayı açın.',
        ]);

        $results['setMyShortDescription'] = self::api('setMyShortDescription', [
            'short_description' => $brand,
        ]);

        $results['setMyCommands'] = self::api('setMyCommands', [
            'commands' => [
                ['command' => 'start', 'description' => $brand],
            ],
        ]);

        $results['setChatMenuButton'] = self::api('setChatMenuButton', [
            'menu_button' => [
                'type' => 'web_app',
                'text' => '▶ ' . $brand,
                'web_app' => ['url' => self::appUrl()],
            ],
        ]);

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public static function setWebhook(string $url, ?string $secret = null): array
    {
        $payload = [
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => false,
        ];
        $secret = $secret ?? self::webhookSecret();
        if ($secret !== '') {
            $payload['secret_token'] = $secret;
        }

        return self::api('setWebhook', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getWebhookInfo(): array
    {
        return self::api('getWebhookInfo');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getMe(): array
    {
        return self::api('getMe');
    }
}
