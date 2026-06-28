<?php

declare(strict_types=1);

final class AdminDataRedactor
{
    public static function isSensitiveColumn(string $column): bool
    {
        return preg_match(
            '/password|secret|token|api_?key|private_?key|wallet_?secret|callback_?secret|raw_payload|request_payload|response_payload|payload|request_body|response_body|callback_body|body_json|headers/i',
            $column
        ) === 1;
    }

    public static function format(string $column, mixed $value, int $limit = 80): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (self::isSensitiveColumn($column)) {
            return '••••••';
        }

        $text = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $text = is_string($text) ? $text : '';

        if (preg_match('/(^|_)currency$/i', $column) === 1 && strtoupper(trim($text)) === 'TRY') {
            return '₺';
        }

        if (preg_match('/amount|balance|fee|price|total/i', $column) === 1 && is_numeric(str_replace(',', '.', $text))) {
            return '₺' . number_format((float) str_replace(',', '.', $text), 2, ',', '.');
        }

        if ($limit > 0 && strlen($text) > $limit) {
            return substr($text, 0, $limit) . '...';
        }

        return $text;
    }
}
