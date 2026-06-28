<?php

/**
 * Betco tarzı JSON zarfı: success, code, message, data.
 */
final class ApiEnvelope
{
    /**
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    public static function data(?array $json): array
    {
        return BackendApiClient::unwrap($json);
    }

    /**
     * @param array<string, mixed>|null $json
     */
    public static function isOk(?array $json): bool
    {
        if ($json === null) {
            return false;
        }
        if (array_key_exists('success', $json) && !filter_var($json['success'], FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed>|null $json
     */
    public static function message(?array $json): string
    {
        if (!is_array($json)) {
            return '';
        }

        return (string) ($json['message'] ?? '');
    }

    /**
     * @param array<string, mixed>|null $json
     */
    public static function code(?array $json): ?int
    {
        if (!is_array($json) || !array_key_exists('code', $json)) {
            return null;
        }

        return is_numeric($json['code']) ? (int) $json['code'] : null;
    }

    /**
     * Zarf geçerli ve success ise data içindeki liste alanını döndürür; aksi halde null.
     * Kayıpsız liste için [] dönebilir.
     *
     * @param array<string, mixed>|null $json
     * @return list<array<string, mixed>>|null
     */
    public static function listFromData(?array $json, string $listKey = 'sliders'): ?array
    {
        if ($json === null) {
            return null;
        }
        if (array_key_exists('success', $json) && !filter_var($json['success'], FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $u = BackendApiClient::unwrap($json);
        $list = $u[$listKey] ?? ($json[$listKey] ?? null);
        if ($list === null && is_array($u) && isset($u[0])) {
            $list = $u;
        }
        if (!is_array($list)) {
            return [];
        }

        return $list;
    }
}
