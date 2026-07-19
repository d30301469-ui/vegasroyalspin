<?php

/** HTML profile detail fragments for bet-history modals (split-deploy backend). */

if (!function_exists('member_profile_html_escape')) {
    function member_profile_html_escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('member_profile_emit_html')) {
    function member_profile_emit_html(int $status, string $html): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo $html;
        exit;
    }
}

if (!function_exists('member_profile_detail_badge')) {
    function member_profile_detail_badge(string $label, string $variant = 'neutral'): string
    {
        $variant = in_array($variant, ['neutral', 'info', 'success', 'warning', 'danger', 'primary'], true) ? $variant : 'neutral';

        return '<span class="profile-detail-badge profile-detail-badge--' . member_profile_html_escape($variant) . '">' . member_profile_html_escape($label) . '</span>';
    }
}

if (!function_exists('member_profile_detail_meta_row')) {
    function member_profile_detail_meta_row(string $label, string $value, bool $code = false): string
    {
        $valueHtml = $code
            ? '<code>' . member_profile_html_escape($value !== '' ? $value : '-') . '</code>'
            : member_profile_html_escape($value !== '' ? $value : '-');

        return '<div class="profile-detail-meta-row">'
            . '<div class="profile-detail-meta-label">' . member_profile_html_escape($label) . '</div>'
            . '<div class="profile-detail-meta-value">' . $valueHtml . '</div>'
            . '</div>';
    }
}

if (!function_exists('member_profile_detail_stat')) {
    function member_profile_detail_stat(string $label, string $value, string $tone = 'neutral'): string
    {
        $tone = in_array($tone, ['neutral', 'info', 'success', 'warning', 'danger', 'primary'], true) ? $tone : 'neutral';

        return '<div class="profile-detail-stat profile-detail-stat--' . member_profile_html_escape($tone) . '">'
            . '<div class="profile-detail-stat__label">' . member_profile_html_escape($label) . '</div>'
            . '<div class="profile-detail-stat__value">' . member_profile_html_escape($value !== '' ? $value : '-') . '</div>'
            . '</div>';
    }
}

if (!function_exists('member_profile_spor_detail_humanize_key')) {
    function member_profile_spor_detail_humanize_key(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return 'Alan';
        }

        $lookup = [
            'id' => 'ID',
            'betid' => 'Bahis ID',
            'bet_id' => 'Bahis ID',
            'transactionid' => 'Transaction ID',
            'transaction_id' => 'Transaction ID',
            'roundid' => 'Round ID',
            'round_id' => 'Round ID',
            'gameid' => 'Oyun ID',
            'game_id' => 'Oyun ID',
            'gamecode' => 'Oyun Kodu',
            'game_code' => 'Oyun Kodu',
            'providername' => 'Sağlayıcı',
            'provider_name' => 'Sağlayıcı',
            'providercode' => 'Provider Kodu',
            'provider_code' => 'Provider Kodu',
            'txn' => 'İşlem',
            'txntype' => 'İşlem Türü',
            'txn_type' => 'İşlem Türü',
            'status' => 'Durum',
            'createdat' => 'Oluşturulma',
            'created_at' => 'Oluşturulma',
            'updatedat' => 'Güncellenme',
            'updated_at' => 'Güncellenme',
            'detail' => 'Detay',
            'details' => 'Detaylar',
        ];

        $normalized = strtolower(str_replace([' ', '-'], '_', $key));
        if (isset($lookup[$normalized])) {
            return $lookup[$normalized];
        }

        $spaced = preg_replace('/(?<!^)[A-Z]/', ' $0', $key);
        $spaced = preg_replace('/[_-]+/', ' ', (string) $spaced);
        $spaced = preg_replace('/\s+/', ' ', (string) $spaced);
        $spaced = trim((string) $spaced);

        return $spaced !== '' ? mb_convert_case($spaced, MB_CASE_TITLE, 'UTF-8') : $key;
    }
}

if (!function_exists('member_profile_spor_detail_format_scalar')) {
    function member_profile_spor_detail_format_scalar(mixed $value, string $key = ''): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Evet' : 'Hayır';
        }

        if (is_int($value) || is_float($value)) {
            $keyLower = strtolower($key);
            if (preg_match('/(amount|balance|price|stake|bet|win|get|payout|payment|cash|total|fee|commission|odd|odds|rate)/i', $keyLower)) {
                return number_format((float) $value, 2) . ' ₺';
            }

            return (string) $value;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '—';
        }

        $keyLower = strtolower($key);
        if (preg_match('/(created|updated|date|time|at)$/i', $keyLower)) {
            $ts = strtotime($text);
            if ($ts !== false) {
                return date('d.m.Y H:i:s', $ts);
            }
        }

        $valueLower = strtolower($text);
        $valueMap = [
            'bet' => 'Bahis',
            'win' => 'Kazanç',
            'cancel' => 'İptal',
            'refund' => 'İade',
            'rollback' => 'Geri Alma',
            'active' => 'Aktif',
            'completed' => 'Tamamlandı',
            'ok' => 'OK',
            'pending' => 'Beklemede',
            'lose' => 'Kaybetti',
            'lost' => 'Kaybetti',
            'open' => 'Açık',
            'closed' => 'Kapalı',
        ];
        if (isset($valueMap[$valueLower])) {
            return $valueMap[$valueLower];
        }

        return $text;
    }
}

if (!function_exists('member_profile_spor_detail_is_assoc')) {
    function member_profile_spor_detail_is_assoc(array $value): bool
    {
        return !array_is_list($value);
    }
}

if (!function_exists('member_profile_spor_detail_render_value')) {
    function member_profile_spor_detail_render_value(mixed $value, string $key = '', int $depth = 0): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '—';
            }

            if (!member_profile_spor_detail_is_assoc($value)) {
                $items = [];
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        $titleSource = '';
                        foreach (['name', 'label', 'title', 'description', 'market', 'selection'] as $candidateKey) {
                            if (isset($item[$candidateKey]) && is_scalar($item[$candidateKey]) && trim((string) $item[$candidateKey]) !== '') {
                                $titleSource = (string) $item[$candidateKey];
                                break;
                            }
                        }

                        $items[] = '<div class="card bg-transparent border-secondary flex-grow-1" style="min-width: 220px;">'
                            . '<div class="card-body py-2 px-3">'
                            . '<div class="fw-semibold text-white mb-2">'
                            . member_profile_html_escape($titleSource !== '' ? $titleSource : 'Öğe ' . ($index + 1))
                            . '</div>'
                            . member_profile_spor_detail_render_block($item, $depth + 1)
                            . '</div>'
                            . '</div>';
                    } else {
                        $items[] = '<span class="badge rounded-pill text-bg-secondary">' . member_profile_html_escape(member_profile_spor_detail_format_scalar($item, $key)) . '</span>';
                    }
                }

                return '<div class="d-flex flex-wrap gap-2">' . implode('', $items) . '</div>';
            }

            return member_profile_spor_detail_render_block($value, $depth + 1);
        }

        return member_profile_html_escape(member_profile_spor_detail_format_scalar($value, $key));
    }
}

if (!function_exists('member_profile_spor_detail_render_block')) {
    function member_profile_spor_detail_render_block(array $data, int $depth = 0): string
    {
        $rows = '';

        foreach ($data as $key => $value) {
            $label = member_profile_spor_detail_humanize_key((string) $key);

            if (is_array($value)) {
                if ($value === []) {
                    $rows .= '<tr><th style="width: 30%;">' . member_profile_html_escape($label) . '</th><td>—</td></tr>';
                    continue;
                }

                $rows .= '<tr><th colspan="2" class="bg-light">' . member_profile_html_escape($label) . '</th></tr>';
                $rows .= '<tr><td colspan="2" style="padding: 0;">'
                    . member_profile_spor_detail_render_value($value, (string) $key, $depth)
                    . '</td></tr>';
                continue;
            }

            $rows .= '<tr><th style="width: 30%;">' . member_profile_html_escape($label) . '</th><td>'
                . member_profile_spor_detail_render_value($value, (string) $key, $depth)
                . '</td></tr>';
        }

        return '<div class="table-responsive">'
            . '<table class="table table-sm table-bordered mb-0">'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '</div>';
    }
}

if (!function_exists('member_profile_normalize_history_id')) {
    function member_profile_normalize_history_id(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (str_starts_with(strtoupper($raw), 'GH_')) {
            return substr($raw, 3);
        }

        return $raw;
    }
}

if (!function_exists('member_profile_render_game_history_detail')) {
    function member_profile_render_game_history_detail(PDO $pdo, int $userId, string $historyId): void
    {
        $historyId = member_profile_normalize_history_id($historyId);
        if ($historyId === '') {
            member_profile_emit_html(200, '<div class="alert alert-danger">Geçersiz istek</div>');
        }

        $source = '';
        $localId = $historyId;
        if (str_contains($historyId, ':')) {
            [$prefix, $rawId] = explode(':', $historyId, 2);
            $source = strtolower(trim((string) $prefix));
            $localId = trim((string) $rawId);
        }
        if ($localId === '' || !ctype_digit($localId)) {
            member_profile_emit_html(200, '<div class="alert alert-danger">Geçersiz istek</div>');
        }

        $id = (int) $localId;
        $row = null;

        if ($source === '' || $source === 'drakon') {
            $stmt = $pdo->prepare("SELECT
                                       t.id,
                                       'drakon' AS source,
                                       t.transaction_id,
                                       t.related_transaction_id,
                                       t.session_id,
                                       t.round_id,
                                       t.game_id,
                                       COALESCE(NULLIF(t.game_name, ''), g.game_name, t.game_id) AS game_name,
                                       COALESCE(g.provider_code, '') AS provider_code,
                                       COALESCE(NULLIF(t.provider_name, ''), g.provider_name, '') AS provider_name,
                                       COALESCE(g.type, 'casino') AS game_category,
                                       COALESCE(g.game_type, 0) AS game_type,
                                       t.txn_type,
                                       t.status,
                                       t.bet_amount,
                                       t.win_amount,
                                       t.after_balance AS balance_after,
                                       t.created_at
                                   FROM drakon_transactions t
                                   LEFT JOIN drakon_games g ON g.game_id = t.game_id
                                   WHERE t.user_id = :user_id AND t.id = :id
                                   LIMIT 1");
            $stmt->execute(['user_id' => $userId, 'id' => $id]);
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($candidate)) {
                $row = $candidate;
            }
        }

        if ($row === null && ($source === '' || $source === 'bgaming')) {
            $stmt = $pdo->prepare("SELECT
                                       t.id,
                                       'bgaming' AS source,
                                       t.casino_tx_id AS transaction_id,
                                       t.original_action_id AS related_transaction_id,
                                       t.session_id,
                                       t.round_id,
                                       t.game_identifier AS game_id,
                                       COALESCE(g.title, t.game_identifier) AS game_name,
                                       COALESCE(NULLIF(g.provider, ''), 'bgaming') AS provider_code,
                                       COALESCE(NULLIF(g.provider, ''), 'BGaming') AS provider_name,
                                       COALESCE(NULLIF(g.category, ''), 'slot') AS game_category,
                                       0 AS game_type,
                                       t.txn_type,
                                       'completed' AS status,
                                       CASE WHEN t.txn_type = 'bet' THEN t.amount ELSE 0 END AS bet_amount,
                                       CASE WHEN t.txn_type = 'bet' THEN 0 ELSE t.amount END AS win_amount,
                                       t.after_balance AS balance_after,
                                       COALESCE(t.processed_at, t.created_at) AS created_at
                                   FROM bgaming_transactions t
                                   LEFT JOIN bgaming_games g ON g.identifier = t.game_identifier
                                   WHERE t.user_id = :user_id AND t.id = :id
                                   LIMIT 1");
            $stmt->execute(['user_id' => $userId, 'id' => $id]);
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($candidate)) {
                $row = $candidate;
            }
        }

        if (!is_array($row)) {
            member_profile_emit_html(200, '<div class="alert alert-warning">Oyun işlemi bulunamadı</div>');
        }

        $providerName = (string) ($row['provider_name'] ?? '');
        $providerCode = (string) ($row['provider_code'] ?? '');
        $gameName = (string) ($row['game_name'] ?? '');
        $txnType = strtolower((string) ($row['txn_type'] ?? 'bet'));
        if ($txnType === 'rollback') {
            $txnType = 'refund';
        } elseif (in_array($txnType, ['promo_win', 'freespins_win'], true)) {
            $txnType = 'win';
        }
        $status = (string) ($row['status'] ?? '');
        $betAmount = (float) ($row['bet_amount'] ?? 0);
        $winAmount = (float) ($row['win_amount'] ?? 0);
        $balanceAfter = $row['balance_after'] ?? null;
        $createdAt = (string) ($row['created_at'] ?? '');
        $sourceText = ((string) ($row['source'] ?? '') === 'bgaming')
            ? 'Slot'
            : ((((string) ($row['game_category'] ?? 'casino') === 'live') || (int) ($row['game_type'] ?? 0) === 1) ? 'Canlı Casino' : 'Slot');
        $transactionLabel = match ($txnType) {
            'win' => 'Kazanç',
            'refund', 'cancel' => 'İade',
            default => 'Bahis',
        };
        $statusLabel = $status !== '' ? $status : 'completed';
        $txnTone = match ($txnType) {
            'win' => 'success',
            'refund', 'cancel' => 'warning',
            default => 'danger',
        };
        $statusTone = in_array(strtolower($statusLabel), ['ok', 'completed', 'tamamlandı', 'tamamlandi'], true) ? 'success' : 'neutral';
        $netAmount = $winAmount - $betAmount;
        $netTone = $netAmount >= 0 ? 'success' : 'danger';
        $balanceText = $balanceAfter !== null && $balanceAfter !== '' ? number_format((float) $balanceAfter, 2) . ' ₺' : '—';
        $roundText = (string) ($row['round_id'] ?? '—');
        $transactionText = (string) ($row['transaction_id'] ?? '—');
        $sessionText = (string) ($row['session_id'] ?? '—');

        ob_start();
        ?>
<div class="profile-detail-shell game-history-details">
    <div class="profile-detail-hero">
        <div class="profile-detail-hero__kicker">Oyun Geçmişi Detayı</div>
        <div class="profile-detail-hero__title"><?= member_profile_html_escape($gameName !== '' ? $gameName : '-') ?></div>
        <div class="profile-detail-hero__subtitle">
            <?= member_profile_html_escape($providerName !== '' ? $providerName : '-') ?>
            <span class="profile-detail-hero__dot">•</span>
            <?= member_profile_html_escape($providerCode !== '' ? $providerCode : '-') ?>
        </div>
        <div class="profile-detail-badges">
            <?= member_profile_detail_badge($sourceText, 'info') ?>
            <?= member_profile_detail_badge($transactionLabel, $txnTone) ?>
            <?= member_profile_detail_badge('Durum: ' . $statusLabel, $statusTone) ?>
        </div>
    </div>

    <div class="profile-detail-grid">
        <section class="profile-detail-panel">
            <div class="profile-detail-panel__title">Oyun Bilgileri</div>
            <div class="profile-detail-meta-list">
                <?= member_profile_detail_meta_row('Kaynak', $sourceText) ?>
                <?= member_profile_detail_meta_row('Oyun', $gameName !== '' ? $gameName : '-') ?>
                <?= member_profile_detail_meta_row('Sağlayıcı', $providerName !== '' ? $providerName : '-') ?>
                <?= member_profile_detail_meta_row('Provider Kodu', $providerCode !== '' ? $providerCode : '-', true) ?>
                <?= member_profile_detail_meta_row('Oyun ID', (string) ($row['game_id'] ?? '-'), true) ?>
            </div>
        </section>

        <section class="profile-detail-panel">
            <div class="profile-detail-panel__title">İşlem Bilgileri</div>
            <div class="profile-detail-meta-list">
                <?= member_profile_detail_meta_row('İşlem', $transactionLabel) ?>
                <?= member_profile_detail_meta_row('Status', $statusLabel) ?>
                <?= member_profile_detail_meta_row('Transaction', $transactionText, true) ?>
                <?= member_profile_detail_meta_row('Round', $roundText, true) ?>
                <?= member_profile_detail_meta_row('Oturum', $sessionText, true) ?>
            </div>
        </section>
    </div>

    <section class="profile-detail-panel">
        <div class="profile-detail-panel__title">Finansal Özet</div>
        <div class="profile-detail-stat-grid profile-detail-stat-grid--four">
            <?= member_profile_detail_stat('Bahis', number_format($betAmount, 2) . ' ₺', 'danger') ?>
            <?= member_profile_detail_stat('Kazanç', number_format($winAmount, 2) . ' ₺', 'success') ?>
            <?= member_profile_detail_stat('Bakiye Sonrası', $balanceText, 'primary') ?>
            <?= member_profile_detail_stat('Net', ($netAmount >= 0 ? '+' : '') . number_format($netAmount, 2) . ' ₺', $netTone) ?>
        </div>
    </section>

    <section class="profile-detail-panel">
        <div class="profile-detail-panel__title">Zaman</div>
        <div class="profile-detail-time">
            <?= $createdAt !== '' ? member_profile_html_escape(date('d.m.Y H:i:s', strtotime($createdAt))) : '—' ?>
        </div>
    </section>
</div>
        <?php
        member_profile_emit_html(200, (string) ob_get_clean());
    }
}

if (!function_exists('member_profile_fetch_spor_bet_row')) {
    /**
     * @return array<string, mixed>|null
     */
    function member_profile_fetch_spor_bet_row(PDO $pdo, int $userId, int $betId): ?array
    {
        if ($betId <= 0 || $userId <= 0) {
            return null;
        }

        // Current provider (BetBy/Sportsbook) — legacy candidates below are no longer written to
        // since the sports migration, but are kept for any pre-migration rows still on file.
        try {
            $stmt = $pdo->prepare(
                "SELECT id, txn_code, wager_id, round_id, vendor_code, game_code, txn_type, amount,
                        after_balance, is_finished, detail, raw_payload, created_at
                 FROM sportsbook_transactions WHERE id = :id AND user_id = :user_id LIMIT 1"
            );
            $stmt->execute(['id' => $betId, 'user_id' => $userId]);
            $sbRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($sbRow)) {
                return member_profile_normalize_sportsbook_row($sbRow);
            }
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), '42S02')) {
                throw $e;
            }
        }

        $candidates = ['spor_bets', 'sports_bets', 'sport_bets', 'okko_bets'];
        foreach ($candidates as $table) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id AND user_id = :user_id LIMIT 1");
                $stmt->execute(['id' => $betId, 'user_id' => $userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $row;
                }
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), '42S02')) {
                    continue;
                }
                throw $e;
            }
        }

        return null;
    }
}

if (!function_exists('member_profile_normalize_sportsbook_row')) {
    /**
     * Maps a `sportsbook_transactions` row (BetBy wallet ledger) onto the field shape the
     * spor bet detail renderer expects (legacy spor_bets-style columns).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    function member_profile_normalize_sportsbook_row(array $row): array
    {
        $txnType = strtolower(trim((string) ($row['txn_type'] ?? 'bet')));
        $amount  = abs((float) ($row['amount'] ?? 0));
        if ($txnType === 'cancel') {
            $status = 3;
        } elseif (!empty($row['is_finished'])) {
            $status = 2;
        } else {
            $status = 1;
        }

        $rawPayload = (string) ($row['raw_payload'] ?? '');
        $decodedPayload = [];
        if ($rawPayload !== '' && $rawPayload !== 'null') {
            $decoded = json_decode($rawPayload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $decodedPayload = $decoded;
            }
        }

        return [
            'id'             => (int) ($row['id'] ?? 0),
            'transaction_id' => (string) ($row['txn_code'] ?? ''),
            'round_id'       => (string) ($row['round_id'] ?? ''),
            'game_code'      => (string) ($row['game_code'] ?? 'sports'),
            'provider_name'  => (string) ($row['vendor_code'] ?? 'sports-betby'),
            'bet_amount'     => $txnType === 'bet' ? $amount : 0,
            'get_amount'     => in_array($txnType, ['win', 'cancel'], true) ? $amount : 0,
            'status'         => $status,
            'created_at'     => (string) ($row['created_at'] ?? ''),
            'balance_after'  => $row['after_balance'] ?? null,
            'spor_details'   => json_encode(array_merge($decodedPayload, [
                'detail' => (string) ($row['detail'] ?? ''),
            ]), JSON_UNESCAPED_UNICODE),
        ];
    }
}

if (!function_exists('member_profile_render_spor_bet_detail')) {
    function member_profile_render_spor_bet_detail(PDO $pdo, int $userId, int $betId): void
    {
        if ($betId <= 0) {
            member_profile_emit_html(200, '<div class="alert alert-danger">Geçersiz istek!</div>');
        }

        $bet = member_profile_fetch_spor_bet_row($pdo, $userId, $betId);
        if (!is_array($bet)) {
            member_profile_emit_html(200, '<div class="alert alert-danger">Bahis bulunamadı veya erişim izniniz yok.</div>');
        }

        $providers = [1 => 'TLT', 2 => 'Nexsus', 3 => 'TBS2', 4 => 'LX'];
        $statuses = [1 => 'Aktif', 2 => 'Tamamlandı', 3 => 'İptal Edildi', 4 => 'Beklemede'];
        $statusColors = [1 => 'success', 2 => 'info', 3 => 'danger', 4 => 'warning'];

        $sporDetails = [];
        $rawDetails = (string) ($bet['spor_details'] ?? '');
        if ($rawDetails !== '' && $rawDetails !== 'null') {
            $decoded = json_decode($rawDetails, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $sporDetails = $decoded;
            }
        }

        $statusClass = $statusColors[(int) ($bet['status'] ?? 0)] ?? 'secondary';
        $providerCode = (int) ($bet['game_provider'] ?? 0);
        $providerLabel = is_string($bet['provider_name'] ?? null) && $bet['provider_name'] !== ''
            ? (string) $bet['provider_name']
            : ($providers[$providerCode] ?? 'Bilinmiyor');
        $statusCode = (int) ($bet['status'] ?? 0);
        $statusLabel = $statuses[$statusCode] ?? 'Bilinmiyor';
        $betAmount = (float) ($bet['bet_amount'] ?? 0);
        $winAmount = (float) ($bet['get_amount'] ?? 0);
        $balanceAfter = $bet['balance_after'] ?? null;
        $netAmount = $winAmount - $betAmount;
        $netTone = $netAmount >= 0 ? 'success' : 'danger';
        $sourceLabel = member_profile_spor_detail_humanize_key('game_code');
        $detailSourceLabel = 'Spor Bahis Detayları';
        $gameCodeText = member_profile_spor_detail_format_scalar((string) ($bet['game_code'] ?? '-'), 'game_code');

        ob_start();
        ?>
<div class="row g-3 game-history-details">
    <div class="col-md-6">
        <h6 class="border-bottom pb-2">Bahis Bilgileri</h6>
        <table class="table table-sm table-bordered mb-0">
            <tr><th style="width: 40%;">Bahis ID:</th><td><strong><?= (int) ($bet['id'] ?? 0) ?></strong></td></tr>
            <tr><th>Transaction ID:</th><td><code><?= member_profile_html_escape((string) ($bet['transaction_id'] ?? '-')) ?></code></td></tr>
            <tr><th>Round ID:</th><td><code><?= member_profile_html_escape((string) ($bet['round_id'] ?? '-')) ?></code></td></tr>
            <tr><th>Oyun Kodu:</th><td><?= member_profile_html_escape((string) ($bet['game_code'] ?? '-')) ?></td></tr>
            <tr><th>Sağlayıcı:</th><td><?= member_profile_html_escape($providerLabel) ?></td></tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6 class="border-bottom pb-2">Finansal Bilgiler</h6>
        <table class="table table-sm table-bordered mb-0">
            <tr><th style="width: 40%;">Bahis Miktarı:</th><td class="fw-bold text-danger"><?= number_format($betAmount, 2) ?> ₺</td></tr>
            <tr><th>Kazanç Miktarı:</th><td class="fw-bold text-success"><?= number_format($winAmount, 2) ?> ₺</td></tr>
            <tr><th>Net:</th><td class="fw-bold <?= $netTone === 'success' ? 'text-success' : 'text-danger' ?>"><?= ($netAmount >= 0 ? '+' : '') . number_format($netAmount, 2) ?> ₺</td></tr>
            <tr><th>Durum:</th><td><span class="badge bg-<?= member_profile_html_escape($statusClass) ?>"><?= member_profile_html_escape($statusLabel) ?></span></td></tr>
            <tr><th>Oluşturulma:</th><td><?= !empty($bet['created_at']) ? member_profile_html_escape(date('d.m.Y H:i:s', strtotime((string) $bet['created_at']))) : '—' ?></td></tr>
            <tr><th>Son Bakiye:</th><td><?= $balanceAfter !== null && $balanceAfter !== '' ? number_format((float) $balanceAfter, 2) . ' ₺' : '-' ?></td></tr>
        </table>
    </div>
</div>
<?php if ($sporDetails !== []): ?>
<div class="mt-3">
    <h6 class="border-bottom pb-2">Spor Bahis Detayları</h6>
    <?= member_profile_spor_detail_render_block($sporDetails) ?>
</div>
<?php else: ?>
<div class="mt-3"><div class="alert alert-info mb-0"><i class="fas fa-info-circle me-2"></i>Bu bahis için detay bilgisi bulunmuyor.</div></div>
<?php endif; ?>
<?php if (!empty($bet['spor_results'])): ?>
<div class="mt-3">
    <h6 class="border-bottom pb-2">Sonuç Analizi</h6>
    <div class="alert alert-info mb-0"><?= nl2br(member_profile_html_escape((string) $bet['spor_results'])) ?></div>
</div>
<?php endif; ?>
        <?php
        member_profile_emit_html(200, (string) ob_get_clean());
    }
}
