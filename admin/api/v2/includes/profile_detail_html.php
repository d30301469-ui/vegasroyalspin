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

        ob_start();
        ?>
<div class="game-history-details">
    <div class="row g-3">
        <div class="col-md-6">
            <h6 class="text-muted">Oyun bilgileri</h6>
            <table class="table table-sm table-bordered mb-0">
                <tr><th style="width:40%;">Kaynak</th><td><?= member_profile_html_escape($sourceText) ?></td></tr>
                <tr><th>Oyun</th><td><?= member_profile_html_escape($gameName !== '' ? $gameName : '-') ?></td></tr>
                <tr><th>Sağlayıcı</th><td><?= member_profile_html_escape($providerName !== '' ? $providerName : '-') ?></td></tr>
                <tr><th>Provider Kodu</th><td><code><?= member_profile_html_escape($providerCode !== '' ? $providerCode : '-') ?></code></td></tr>
                <tr><th>Oyun ID</th><td><code><?= member_profile_html_escape((string) ($row['game_id'] ?? '-')) ?></code></td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="text-muted">İşlem bilgileri</h6>
            <table class="table table-sm table-bordered mb-0">
                <tr><th style="width:40%;">İşlem</th><td><?= member_profile_html_escape($transactionLabel) ?></td></tr>
                <tr><th>Status</th><td><?= member_profile_html_escape($status !== '' ? $status : 'completed') ?></td></tr>
                <tr><th>Transaction</th><td><code><?= member_profile_html_escape((string) ($row['transaction_id'] ?? '-')) ?></code></td></tr>
                <tr><th>Round</th><td><code><?= member_profile_html_escape((string) ($row['round_id'] ?? '-')) ?></code></td></tr>
                <tr><th>Oturum</th><td><code><?= member_profile_html_escape((string) ($row['session_id'] ?? '-')) ?></code></td></tr>
            </table>
        </div>
        <div class="col-md-12">
            <h6 class="text-muted">Finansal özet</h6>
            <div class="row g-2">
                <div class="col-md-4"><div class="card bg-light"><div class="card-body py-2"><small class="text-muted">Bahis</small><div class="fw-bold text-danger"><?= number_format($betAmount, 2) ?> ₺</div></div></div></div>
                <div class="col-md-4"><div class="card bg-light"><div class="card-body py-2"><small class="text-muted">Kazanç</small><div class="fw-bold text-success"><?= number_format($winAmount, 2) ?> ₺</div></div></div></div>
                <div class="col-md-4"><div class="card bg-light"><div class="card-body py-2"><small class="text-muted">Bakiye Sonrası</small><div class="fw-bold text-primary"><?= number_format((float) $balanceAfter, 2) ?> ₺</div></div></div></div>
            </div>
        </div>
        <div class="col-md-12">
            <h6 class="text-muted">Zaman</h6>
            <div class="alert alert-secondary mb-0"><?= $createdAt !== '' ? member_profile_html_escape(date('d.m.Y H:i:s', strtotime($createdAt))) : '—' ?></div>
        </div>
    </div>
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

        ob_start();
        ?>
<div class="row">
    <div class="col-md-6">
        <h6 class="border-bottom pb-2">Bahis Bilgileri</h6>
        <table class="table table-sm table-bordered">
            <tr><th style="width: 40%;">Bahis ID:</th><td><strong><?= (int) ($bet['id'] ?? 0) ?></strong></td></tr>
            <tr><th>Transaction ID:</th><td><code><?= member_profile_html_escape((string) ($bet['transaction_id'] ?? '')) ?></code></td></tr>
            <tr><th>Round ID:</th><td><code><?= member_profile_html_escape((string) ($bet['round_id'] ?? '')) ?></code></td></tr>
            <tr><th>Oyun Kodu:</th><td><?= member_profile_html_escape((string) ($bet['game_code'] ?? '')) ?></td></tr>
            <tr><th>Sağlayıcı:</th><td><?= member_profile_html_escape($providerLabel) ?></td></tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6 class="border-bottom pb-2">Finansal Bilgiler</h6>
        <table class="table table-sm table-bordered">
            <tr><th style="width: 40%;">Bahis Miktarı:</th><td class="fw-bold text-danger"><?= number_format((float) ($bet['bet_amount'] ?? 0), 2) ?> ₺</td></tr>
            <tr><th>Kazanç Miktarı:</th><td class="fw-bold text-success"><?= number_format((float) ($bet['get_amount'] ?? 0), 2) ?> ₺</td></tr>
            <tr><th>Durum:</th><td><span class="badge bg-<?= member_profile_html_escape($statusClass) ?>"><?= member_profile_html_escape($statuses[$statusCode] ?? 'Bilinmiyor') ?></span></td></tr>
            <tr><th>Oluşturulma:</th><td><?= !empty($bet['created_at']) ? member_profile_html_escape(date('d.m.Y H:i:s', strtotime((string) $bet['created_at']))) : '—' ?></td></tr>
            <tr><th>Son Bakiye:</th><td><?= !empty($bet['balance_after']) ? number_format((float) $bet['balance_after'], 2) . ' ₺' : '-' ?></td></tr>
        </table>
    </div>
</div>
<?php if ($sporDetails !== []): ?>
<div class="mt-3">
    <h6 class="border-bottom pb-2">Spor Bahis Detayları</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <tbody>
            <?php foreach ($sporDetails as $key => $value): ?>
                <?php if (is_array($value)): ?>
                <tr><th colspan="2" class="bg-light"><?= member_profile_html_escape((string) $key) ?></th></tr>
                    <?php foreach ($value as $subKey => $subValue): ?>
                    <tr>
                        <td style="width: 30%; padding-left: 30px;"><?= member_profile_html_escape((string) $subKey) ?></td>
                        <td><?= is_array($subValue) ? '<pre class="mb-0" style="font-size: 11px;">' . member_profile_html_escape(json_encode($subValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>' : member_profile_html_escape((string) $subValue) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                <tr><th style="width: 30%;"><?= member_profile_html_escape((string) $key) ?></th><td><?= member_profile_html_escape((string) $value) ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="mt-3"><div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Bu bahis için detay bilgisi bulunmuyor.</div></div>
<?php endif; ?>
<?php if (!empty($bet['spor_results'])): ?>
<div class="mt-3">
    <h6 class="border-bottom pb-2">Sonuç Analizi</h6>
    <div class="alert alert-info"><?= nl2br(member_profile_html_escape((string) $bet['spor_results'])) ?></div>
</div>
<?php endif; ?>
        <?php
        member_profile_emit_html(200, (string) ob_get_clean());
    }
}
