<?php

declare(strict_types=1);

/**
 * Append-only Gaming Soft / GSC+ diagnostics under project root logs/gamingsoft/.
 * Secrets (secret_key, sign, passwords) are stripped before write.
 */
final class GscFileLog
{
    private const SENSITIVE_KEYS = [
        'secret_key', 'secret', 'sign', 'password', 'passwd', 'token', 'authorization',
    ];

    public static function dir(): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);
        return rtrim(str_replace('\\', '/', $base), '/') . '/logs/gamingsoft';
    }

    /** @param array<string,mixed> $context */
    public static function write(string $channel, string $event, array $context = []): void
    {
        try {
            $dir = self::dir();
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return;
            }
            $row = [
                'ts' => gmdate('Y-m-d\\TH:i:s\\Z'),
                'channel' => $channel,
                'event' => $event,
                'context' => self::redact($context),
            ];
            $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($line)) {
                return;
            }
            $dayFile = $dir . '/gsc_' . gmdate('Ymd') . '.log';
            $latest = $dir . '/latest.log';
            @file_put_contents($dayFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            @file_put_contents($latest, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Never break gameplay / callbacks over logging.
        }
    }

    /** @param mixed $value */
    private static function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $key => $item) {
            $k = is_string($key) ? strtolower($key) : (string) $key;
            if (in_array($k, self::SENSITIVE_KEYS, true) || str_contains($k, 'secret') || str_contains($k, 'password')) {
                $out[$key] = is_string($item) ? ('***len=' . strlen($item)) : '***';
                continue;
            }
            $out[$key] = self::redact($item);
        }
        return $out;
    }
}
