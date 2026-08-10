<?php

declare(strict_types=1);

/**
 * Bookiewise-style advanced filters for the admin players (users) list.
 * Query params: pf[username], pf[player_type], …
 */
final class PlayersListFilter
{
    /**
     * @param array<string, mixed> $raw
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, string>, 3: int}
     */
    public static function build(array $raw): array
    {
        $parts = [];
        $params = [];
        $active = [];
        $count = 0;
        $i = 0;

        $like = static function (string $column, string $key, string $value) use (&$parts, &$params, &$active, &$count, &$i): void {
            $value = trim($value);
            if ($value === '') {
                return;
            }
            $i++;
            $param = 'pf_like_' . $i;
            $parts[] = $column . ' LIKE :' . $param;
            $params[$param] = '%' . $value . '%';
            $active[$key] = $value;
            $count++;
        };

        $dateRange = static function (string $column, string $startKey, string $endKey, string $start, string $end) use (&$parts, &$params, &$active, &$count, &$i): void {
            $start = self::normalizeDate($start);
            $end = self::normalizeDate($end);
            if ($start !== '') {
                $i++;
                $param = 'pf_ds_' . $i;
                $parts[] = $column . ' >= :' . $param;
                $params[$param] = $start . ' 00:00:00';
                $active[$startKey] = $start;
                $count++;
            }
            if ($end !== '') {
                $i++;
                $param = 'pf_de_' . $i;
                $parts[] = $column . ' <= :' . $param;
                $params[$param] = $end . ' 23:59:59';
                $active[$endKey] = $end;
                $count++;
            }
        };

        $get = static function (string $key) use ($raw): string {
            $value = $raw[$key] ?? '';
            if (is_array($value)) {
                $value = (string) reset($value);
            }
            return trim((string) $value);
        };

        $like('username', 'username', $get('username'));
        $like('name', 'name', $get('name'));
        $like('surname', 'surname', $get('surname'));
        $like('email', 'email', $get('email'));
        $like('phone', 'phone', $get('phone'));
        $like('identity_number', 'identity', $get('identity'));
        $like('country', 'currency', $get('currency'));

        $playerType = strtolower($get('player_type'));
        if ($playerType === 'real' || $playerType === 'gercek' || $playerType === 'gerçek') {
            $parts[] = 'COALESCE(is_test, 0) = 0';
            $active['player_type'] = 'real';
            $count++;
        } elseif ($playerType === 'test') {
            $parts[] = 'COALESCE(is_test, 0) = 1';
            $active['player_type'] = 'test';
            $count++;
        }

        $tag = strtoupper($get('tag'));
        if ($tag !== '' && preg_match('/^[A-Z0-9]$/u', $tag) === 1) {
            $i++;
            $param = 'pf_tag_' . $i;
            $parts[] = 'UPPER(LEFT(TRIM(username), 1)) = :' . $param;
            $params[$param] = $tag;
            $active['tag'] = $tag;
            $count++;
        }

        $bonus = strtolower($get('bonus'));
        if ($bonus === 'yes' || $bonus === 'var' || $bonus === '1') {
            $parts[] = '(COALESCE(bonus_balance, 0) > 0 OR EXISTS (SELECT 1 FROM user_active_bonuses uab WHERE uab.user_id = users.id AND LOWER(COALESCE(uab.status, \'\')) IN (\'active\', \'pending\', \'running\')))';
            $active['bonus'] = 'yes';
            $count++;
        } elseif ($bonus === 'no' || $bonus === 'yok' || $bonus === '0') {
            $parts[] = '(COALESCE(bonus_balance, 0) <= 0 AND NOT EXISTS (SELECT 1 FROM user_active_bonuses uab WHERE uab.user_id = users.id AND LOWER(COALESCE(uab.status, \'\')) IN (\'active\', \'pending\', \'running\')))';
            $active['bonus'] = 'no';
            $count++;
        }

        $hasBalance = strtolower($get('has_balance'));
        if ($hasBalance === 'yes' || $hasBalance === '1' || $hasBalance === 'var') {
            $parts[] = 'COALESCE(balance, 0) > 0';
            $active['has_balance'] = 'yes';
            $count++;
        } elseif ($hasBalance === 'no' || $hasBalance === '0' || $hasBalance === 'yok') {
            $parts[] = 'COALESCE(balance, 0) <= 0';
            $active['has_balance'] = 'no';
            $count++;
        }

        $gender = self::normalizeGender($get('gender'));
        if ($gender !== '') {
            if ($gender === 'male') {
                $parts[] = '(LOWER(COALESCE(gender, \'\')) IN (\'male\', \'erkek\', \'m\', \'e\'))';
            } else {
                $parts[] = '(LOWER(COALESCE(gender, \'\')) IN (\'female\', \'kadin\', \'kadın\', \'f\', \'k\'))';
            }
            $active['gender'] = $gender;
            $count++;
        }

        $activeFilter = strtolower($get('active'));
        if (in_array($activeFilter, ['1', 'yes', 'aktif', 'active'], true)) {
            $parts[] = 'COALESCE(banned, 0) = 0';
            $active['active'] = '1';
            $count++;
        } elseif (in_array($activeFilter, ['0', 'no', 'pasif', 'inactive', 'inaktif'], true)) {
            $parts[] = 'COALESCE(banned, 0) = 1';
            $active['active'] = '0';
            $count++;
        }

        $partner = $get('partner');
        if ($partner !== '') {
            $i++;
            $param = 'pf_partner_' . $i;
            $parts[] = 'EXISTS (
                SELECT 1 FROM affiliates a
                WHERE a.id = users.referred_by_affiliate_id
                  AND (
                    a.referral_code LIKE :' . $param . '
                    OR a.full_name LIKE :' . $param . '
                    OR a.company_name LIKE :' . $param . '
                    OR a.email LIKE :' . $param . '
                  )
            )';
            $params[$param] = '%' . $partner . '%';
            $active['partner'] = $partner;
            $count++;
        }

        $lastIp = $get('last_login_ip');
        if ($lastIp !== '') {
            $i++;
            $param = 'pf_ip_' . $i;
            $parts[] = 'EXISTS (
                SELECT 1 FROM member_jwt_tokens t
                WHERE t.user_id = users.id
                  AND t.ip_address LIKE :' . $param . '
                  AND t.id = (
                    SELECT t2.id FROM member_jwt_tokens t2
                    WHERE t2.user_id = users.id
                    ORDER BY t2.issued_at DESC, t2.id DESC
                    LIMIT 1
                  )
            )';
            $params[$param] = '%' . $lastIp . '%';
            $active['last_login_ip'] = $lastIp;
            $count++;
        }

        $dateRange('created_at', 'created_from', 'created_to', $get('created_from'), $get('created_to'));
        $dateRange('last_login_at', 'login_from', 'login_to', $get('login_from'), $get('login_to'));

        $firstDepFrom = self::normalizeDate($get('first_deposit_from'));
        $firstDepTo = self::normalizeDate($get('first_deposit_to'));
        if ($firstDepFrom !== '' || $firstDepTo !== '') {
            $sub = "SELECT MIN(mt.created_at) FROM megapayz_transactions mt WHERE mt.user_id = users.id AND mt.type = 'deposit' AND LOWER(COALESCE(mt.status, '')) IN ('confirmed', 'success', 'completed', 'approved')";
            if ($firstDepFrom !== '') {
                $i++;
                $param = 'pf_fd_from_' . $i;
                $parts[] = '(' . $sub . ') >= :' . $param;
                $params[$param] = $firstDepFrom . ' 00:00:00';
                $active['first_deposit_from'] = $firstDepFrom;
                $count++;
            }
            if ($firstDepTo !== '') {
                $i++;
                $param = 'pf_fd_to_' . $i;
                $parts[] = '(' . $sub . ') <= :' . $param;
                $params[$param] = $firstDepTo . ' 23:59:59';
                $active['first_deposit_to'] = $firstDepTo;
                $count++;
            }
        }

        $lastDepFrom = self::normalizeDate($get('last_deposit_from'));
        $lastDepTo = self::normalizeDate($get('last_deposit_to'));
        if ($lastDepFrom !== '' || $lastDepTo !== '') {
            $sub = "SELECT MAX(mt.created_at) FROM megapayz_transactions mt WHERE mt.user_id = users.id AND mt.type = 'deposit' AND LOWER(COALESCE(mt.status, '')) IN ('confirmed', 'success', 'completed', 'approved')";
            if ($lastDepFrom !== '') {
                $i++;
                $param = 'pf_ld_from_' . $i;
                $parts[] = '(' . $sub . ') >= :' . $param;
                $params[$param] = $lastDepFrom . ' 00:00:00';
                $active['last_deposit_from'] = $lastDepFrom;
                $count++;
            }
            if ($lastDepTo !== '') {
                $i++;
                $param = 'pf_ld_to_' . $i;
                $parts[] = '(' . $sub . ') <= :' . $param;
                $params[$param] = $lastDepTo . ' 23:59:59';
                $active['last_deposit_to'] = $lastDepTo;
                $count++;
            }
        }

        $dobFromD = $get('dob_from_d');
        $dobFromM = $get('dob_from_m');
        $dobFromY = $get('dob_from_y');
        $dobFrom = self::composeDob($dobFromD, $dobFromM, $dobFromY);
        if ($dobFrom !== '') {
            $i++;
            $param = 'pf_dob_from_' . $i;
            $parts[] = 'dob >= :' . $param;
            $params[$param] = $dobFrom;
            $active['dob_from_d'] = $dobFromD;
            $active['dob_from_m'] = $dobFromM;
            $active['dob_from_y'] = $dobFromY;
            $count++;
        }

        $dobToD = $get('dob_to_d');
        $dobToM = $get('dob_to_m');
        $dobToY = $get('dob_to_y');
        $dobTo = self::composeDob($dobToD, $dobToM, $dobToY);
        if ($dobTo !== '') {
            $i++;
            $param = 'pf_dob_to_' . $i;
            $parts[] = 'dob <= :' . $param;
            $params[$param] = $dobTo;
            $active['dob_to_d'] = $dobToD;
            $active['dob_to_m'] = $dobToM;
            $active['dob_to_y'] = $dobToY;
            $count++;
        }

        $verified = strtolower($get('verified'));
        if (in_array($verified, ['1', 'yes', 'on', 'true'], true)) {
            $parts[] = 'COALESCE(is_verified, 0) = 1';
            $active['verified'] = '1';
            $count++;
        }

        if ($parts === []) {
            return ['', [], [], 0];
        }

        return ['(' . implode(') AND (', $parts) . ')', $params, $active, $count];
    }

    /**
     * @param array<string, string> $active
     */
    public static function queryString(array $active, int $perPage = 25, string $search = '', int $page = 1): string
    {
        $params = ['key' => 'users', 'per_page' => $perPage];
        if ($search !== '') {
            $params['search'] = $search;
        }
        if ($page > 1) {
            $params['page'] = $page;
        }
        foreach ($active as $key => $value) {
            if ($value === '') {
                continue;
            }
            $params['pf[' . $key . ']'] = $value;
        }

        return http_build_query($params);
    }

    private static function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m) === 1) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    private static function composeDob(string $d, string $m, string $y): string
    {
        $d = preg_replace('/\D+/', '', $d) ?? '';
        $m = preg_replace('/\D+/', '', $m) ?? '';
        $y = preg_replace('/\D+/', '', $y) ?? '';
        if ($d === '' || $m === '' || $y === '' || strlen($y) !== 4) {
            return '';
        }
        $day = (int) $d;
        $month = (int) $m;
        $year = (int) $y;
        if (!checkdate($month, $day, $year)) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function normalizeGender(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        if (in_array($value, ['male', 'erkek', 'm', 'e'], true)) {
            return 'male';
        }
        if (in_array($value, ['female', 'kadin', 'kadın', 'f', 'k'], true)) {
            return 'female';
        }

        return '';
    }
}
