<?php

/**
 * T.C. kimlik no: 10. ve 11. hane kontrolü (api.md / App\Support\TurkishNationalId ile uyumlu mantık).
 */
final class TurkishNationalId
{
    public static function isValid(string $tc): bool
    {
        if (!preg_match('/^\d{11}$/', $tc)) {
            return false;
        }
        if ($tc[0] === '0') {
            return false;
        }

        $d = array_map('intval', str_split($tc));

        $oddSum = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $evenSum = $d[1] + $d[3] + $d[5] + $d[7];
        $d10 = ((($oddSum * 7) - $evenSum) % 10 + 10) % 10;
        if ($d[9] !== $d10) {
            return false;
        }

        $sum10 = array_sum(array_slice($d, 0, 10));

        return ($sum10 % 10) === $d[10];
    }
}
