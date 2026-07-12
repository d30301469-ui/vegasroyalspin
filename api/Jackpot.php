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
        $apiProviders = is_array($config['providers'] ?? null) ? $config['providers'] : [];
        $defaultProviders = is_array($defaults['providers'] ?? null) ? $defaults['providers'] : [];

        if ($apiProviders === []) {
            $providers = $defaultProviders;
        } else {
            // Default increment haritası: provider_id → tier_name → increment
            $defIncMap = [];
            foreach ($defaultProviders as $dp) {
                $dpId = strtolower((string) ($dp['id'] ?? ''));
                foreach ($dp['tiers'] ?? [] as $dt) {
                    $tn = strtolower((string) ($dt['name'] ?? ''));
                    if ($dpId !== '' && $tn !== '') {
                        $defIncMap[$dpId][$tn] = (float) ($dt['increment'] ?? 0);
                    }
                }
            }
            // API miktarlarını koru, increment yoksa veya 0 ise varsayılanı kullan
            $providers = [];
            foreach ($apiProviders as $p) {
                $pid = strtolower((string) ($p['id'] ?? ''));
                $tiers = [];
                foreach ($p['tiers'] ?? [] as $tier) {
                    $tn  = strtolower((string) ($tier['name'] ?? ''));
                    $inc = (float) ($tier['increment'] ?? 0);
                    if ($inc <= 0 && isset($defIncMap[$pid][$tn])) {
                        $inc = $defIncMap[$pid][$tn];
                    }
                    $tiers[] = array_merge($tier, ['increment' => $inc]);
                }
                $providers[] = array_merge($p, ['tiers' => $tiers]);
            }
        }

        return [
            'epoch'     => $epoch !== '' ? $epoch : (string) ($defaults['epoch'] ?? date('Y-m-d H:i:s')),
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
