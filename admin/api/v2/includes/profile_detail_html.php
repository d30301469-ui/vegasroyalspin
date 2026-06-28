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

        DrakonService::bootstrap($pdo);
        $stmt = $pdo->prepare("SELECT
                                   t.id,
                                   t.transaction_id,
                                   t.session_id,
                                   t.round_id,
                                   t.game_id,
                                   COALESCE(NULLIF(t.game_name, ''), g.game_name, t.game_id) AS game_name,
                                   COALESCE(g.provider_code, '') AS provider_code,
                                   COALESCE(NULLIF(t.provider_name, ''), g.provider_name, '') AS provider_name,
                                   t.txn_type,
                                   t.status,
                                   t.bet_amount,
                                   t.win_amount,
                                   t.after_balance AS balance_after,
                                   t.created_at
                               FROM drakon_transactions t
                               LEFT JOIN drakon_games g ON g.game_id = t.game_id
                               WHERE t.user_id = :user_id
                                 AND CAST(t.id AS CHAR) = :history_id
                               LIMIT 1");
        $stmt->execute(['user_id' => $userId, 'history_id' => $historyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            member_profile_emit_html(200, '<div class="alert alert-warning">Oyun işlemi bulunamadı</div>');
        }

        $gName = (string) ($row['game_name'] ?? '');
        $pName = (string) ($row['provider_name'] ?? '');
        $betAmount = (float) ($row['bet_amount'] ?? 0);
        $winAmount = (float) ($row['win_amount'] ?? 0);
        $txnType = (string) ($row['txn_type'] ?? '');
        $status = (string) ($row['status'] ?? '');
        $createdAt = (string) ($row['created_at'] ?? '');
        $balanceAfter = $row['balance_after'] ?? null;
        $roundId = (string) ($row['round_id'] ?? '');
        $provTxn = (string) ($row['transaction_id'] ?? '');
        $sessionTok = (string) ($row['session_id'] ?? '');
        $gameId = (string) ($row['game_id'] ?? '');
        $provCode = (string) ($row['provider_code'] ?? '');
        $net = $winAmount - $betAmount;

        ob_start();
        ?>
<div class="game-history-details">
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <h6 class="text-muted">Oyun bilgileri</h6>
                <div class="card bg-light">
                    <div class="card-body">
                        <p class="mb-1"><strong>Oyun:</strong> <?= member_profile_html_escape($gName !== '' ? $gName : '—') ?></p>
                        <p class="mb-1"><strong>Sağlayıcı:</strong> <?= member_profile_html_escape($pName !== '' ? $pName : '—') ?></p>
                        <p class="mb-1"><strong>Game ID:</strong> <?= member_profile_html_escape($gameId !== '' ? $gameId : '—') ?></p>
                        <p class="mb-1"><strong>Sağlayıcı kodu:</strong> <?= member_profile_html_escape($provCode !== '' ? $provCode : '—') ?></p>
                        <p class="mb-1"><strong>Round:</strong> <?= member_profile_html_escape($roundId !== '' ? $roundId : '—') ?></p>
                        <p class="mb-1"><strong>Kaynak:</strong> casino</p>
                        <p class="mb-1"><strong>Cüzdan:</strong> main</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <h6 class="text-muted">İşlem</h6>
                <div class="card bg-light">
                    <div class="card-body">
                        <p class="mb-1"><strong>İşlem türü:</strong> <?= member_profile_html_escape($txnType !== '' ? $txnType : '—') ?></p>
                        <p class="mb-1"><strong>Durum:</strong> <?= member_profile_html_escape($status !== '' ? $status : '—') ?></p>
                        <p class="mb-1"><strong>Bahis:</strong> <span class="text-danger">-<?= number_format($betAmount, 2) ?> ₺</span></p>
                        <p class="mb-1"><strong>Kazanç:</strong> <span class="text-success">+<?= number_format($winAmount, 2) ?> ₺</span></p>
                        <p class="mb-1"><strong>Net:</strong>
                            <span class="<?= $net >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $net >= 0 ? '+' : '' ?><?= number_format($net, 2) ?> ₺
                            </span>
                        </p>
                        <?php if ($balanceAfter !== null && $balanceAfter !== ''): ?>
                        <p class="mb-1"><strong>İşlem sonrası bakiye:</strong> <?= number_format((float) $balanceAfter, 2) ?> ₺</p>
                        <?php endif; ?>
                        <p class="mb-1"><strong>Sağlayıcı işlem no:</strong> <small><?= member_profile_html_escape($provTxn !== '' ? $provTxn : '—') ?></small></p>
                        <?php if ($sessionTok !== ''): ?>
                        <p class="mb-1"><strong>Oturum:</strong> <small><?= member_profile_html_escape($sessionTok) ?></small></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="mb-3">
                <h6 class="text-muted">Zaman</h6>
                <div class="card bg-light">
                    <div class="card-body">
                        <p class="mb-0"><strong>Oluşturulma:</strong>
                            <?= $createdAt !== '' ? member_profile_html_escape(date('d.m.Y H:i:s', strtotime($createdAt))) : '—' ?>
                        </p>
                    </div>
                </div>
            </div>
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
            <tr><th>Sağlayıcı:</th><td><?= member_profile_html_escape($providers[$providerCode] ?? 'Bilinmiyor') ?></td></tr>
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
