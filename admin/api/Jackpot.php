<?php

/**
 * Jackpot widget configuration — admin footer payload (jackpot_config) veya config/jackpot.php fallback.
 */
final class ApiJackpot
{
    public static function fetch(): array
    {
        $defaults = self::defaultsFromConfigFile();

        try {
            if (!class_exists('ApiFooter', false)) {
                require_once __DIR__ . '/Footer.php';
            }
            $footer = ApiFooter::fetch();
            $config = is_array($footer['jackpot_config'] ?? null) ? $footer['jackpot_config'] : [];
            if ($config !== []) {
                $normalized = self::normalize($config, $defaults);
                if (($normalized['providers'] ?? []) !== []) {
                    return $normalized;
                }
            }
        } catch (Throwable) {
            // fallback below
        }

        return $defaults;
    }

    public static function normalize(array $config, array $defaults): array
    {
        $epoch = trim((string) ($config['epoch'] ?? $defaults['epoch'] ?? ''));
        $providers = is_array($config['providers'] ?? null) ? $config['providers'] : [];
        if ($providers === []) {
            $providers = $defaults['providers'] ?? [];
        }

        return [
            'epoch' => $epoch !== '' ? $epoch : (string) ($defaults['epoch'] ?? date('Y-m-d H:i:s')),
            'providers' => $providers,
        ];
    }

    private static function defaultsFromConfigFile(): array
    {
        $jackpotEpoch = null;
        $providers = null;
        $configPath = defined('CONFIG_PATH') ? CONFIG_PATH . '/jackpot.php' : dirname(__DIR__) . '/config/jackpot.php';
        if (is_file($configPath)) {
            require $configPath;
        }

        return [
            'epoch' => (string) ($jackpotEpoch ?? date('Y-m-d H:i:s')),
            'providers' => is_array($providers ?? null) ? $providers : [],
        ];
    }
}
