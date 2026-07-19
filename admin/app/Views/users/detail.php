<?php

$user = is_array($user ?? null) ? $user : [];
$summary = is_array($summary ?? null) ? $summary : [];
$deposits = is_array($deposits ?? null) ? $deposits : [];
$withdrawals = is_array($withdrawals ?? null) ? $withdrawals : [];
$adjustments = is_array($adjustments ?? null) ? $adjustments : [];
$games = is_array($games ?? null) ? $games : [];
$sportsbookCoupons = is_array($sportsbookCoupons ?? null) ? $sportsbookCoupons : [];
$bonusClaims = is_array($bonusClaims ?? null) ? $bonusClaims : [];
$activeBonuses = is_array($activeBonuses ?? null) ? $activeBonuses : [];
$accountWagering = is_array($accountWagering ?? null) ? $accountWagering : [];
$notes = is_array($notes ?? null) ? $notes : [];
$sessions = is_array($sessions ?? null) ? $sessions : [];
$flash = trim((string) ($flash ?? ''));
$userId = (string) ($user['id'] ?? '');
$username = (string) ($user['username'] ?? '');

$money = static fn (mixed $value): string => '₺' . number_format((float) $value, 2, ',', '.');
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$badgeClass = static function (mixed $value): string {
    $value = strtolower((string) $value);
    return match (true) {
        in_array($value, ['active', 'confirmed', 'approved', 'success', '1', 'completed', 'win', 'kazanç'], true) => 'success dot',
        in_array($value, ['pending', 'waiting_approval', 'draft'], true) => 'warning dot',
        in_array($value, ['rejected', 'inactive', 'failed', 'cancelled', 'banned', '0', 'bet', 'kayıp'], true) => 'danger dot',
        in_array($value, ['cancel', 'rollback', 'iptal'], true) => 'warning dot',
        default => 'primary',
    };
};
$txnTypeLabel = static function (mixed $value): string {
    $value = strtolower(trim((string) $value));
    return match ($value) {
        'bet', 'promo_bet' => 'Kayıp',
        'win', 'promo_win', 'freespins_win' => 'Kazanç',
        'cancel', 'rollback' => 'İptal',
        default => $value !== '' ? ucfirst($value) : '-',
    };
};
$renderRows = static function (array $rows, array $columns) use ($text, $money, $badgeClass): void {
    if ($rows === []) {
        echo '<tr><td colspan="' . (count($columns)) . '">Kayıt bulunamadı.</td></tr>';
        return;
    }

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column => $label) {
            $value = $row[$column] ?? '';
            echo '<td style="overflow-wrap:anywhere">';
            if (preg_match('/amount|balance|fee/i', (string) $column) === 1) {
                echo '<span class="data-cell-mono">' . $text($money($value)) . '</span>';
            } elseif (preg_match('/status|action/i', (string) $column) === 1) {
                echo '<span class="badge ' . $text($badgeClass($value)) . '">' . $text($value) . '</span>';
            } else {
                echo $text($value);
            }
            echo '</td>';
        }
        echo '</tr>';
    }
};
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Üyeler · Detay</span>
        <h1 class="hero-title"><?= $text($username) ?> <span class="accent">detay</span></h1>
        <p class="hero-sub">Kullanıcının profil bilgileri, yatırımları, çekimleri, oyun hareketleri, bonusları ve admin bakiye işlemleri tek ekranda toplandı.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/module?key=users'), ENT_QUOTES, 'UTF-8') ?>">Üyelere dön</a>
        <?php $userEditUrl = AdminAuth::url('/user/edit?id=' . rawurlencode($userId)); ?>
        <a class="btn btn--primary" href="<?= htmlspecialchars($userEditUrl, ENT_QUOTES, 'UTF-8') ?>" data-admin-modal-url="<?= htmlspecialchars($userEditUrl, ENT_QUOTES, 'UTF-8') ?>" data-admin-modal-title="<?= $text($username . ' düzenle') ?>">Bilgileri düzenle</a>
    </div>
</div>

<style>
    .user-detail-page { display:flex; flex-direction:column; gap:18px; }
    .user-detail-top { display:grid; grid-template-columns:minmax(280px, 35fr) minmax(0, 65fr); gap:18px; align-items:stretch; }
    .user-detail-top > .card { height:100%; display:flex; flex-direction:column; }
    .user-profile-stack { display:flex; flex-direction:column; gap:14px; }
    .user-profile-row { display:grid; grid-template-columns:110px minmax(0, 1fr); gap:10px; align-items:center; }
    .user-profile-label { color:var(--t-light); font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
    .user-profile-value { min-width:0; color:var(--t-base); font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .user-stat-grid { display:grid; grid-template-columns:repeat(2, minmax(150px, 1fr)); gap:12px; margin-bottom:18px; }
    .user-stat-card { border:1px solid var(--border-soft); border-radius:16px; background:var(--bg-muted); padding:14px; min-width:0; }
    .user-stat-card span { display:block; color:var(--t-light); font-size:11px; font-weight:700; letter-spacing:.06em; margin-bottom:6px; text-transform:uppercase; }
    .user-stat-card strong { display:block; color:var(--t-base); font-size:18px; line-height:1.25; overflow-wrap:anywhere; }
    .user-balance-form { display:grid; grid-template-columns:repeat(4, minmax(140px, 1fr)); gap:14px; align-items:end; }
    .user-balance-form .form-actions { grid-column:1 / -1; display:flex; align-items:center; gap:12px; margin-top:2px; }
    .user-detail-section { width:100%; }
    .user-detail-section .user-stat-grid { grid-template-columns:repeat(4, minmax(150px, 1fr)); }
    .user-game-cell { display:flex; align-items:center; gap:10px; min-width:0; }
    .user-game-thumb { width:42px; height:42px; border-radius:10px; object-fit:cover; background:var(--bg-muted); border:1px solid var(--border); flex:0 0 auto; }
    .user-game-meta { min-width:0; }
    .user-game-name { color:var(--t-base); font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .user-game-provider { color:var(--t-light); font-size:11px; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    @media (max-width:1180px) {
        .user-detail-section .user-stat-grid { grid-template-columns:repeat(2, minmax(180px, 1fr)); }
    }
    @media (max-width:900px) {
        .user-detail-top { grid-template-columns:1fr; }
        .user-stat-grid, .user-balance-form { grid-template-columns:repeat(2, minmax(180px, 1fr)); }
    }
    @media (max-width:720px) {
        .user-stat-grid, .user-balance-form { grid-template-columns:1fr; }
        .user-profile-row { grid-template-columns:1fr; gap:4px; }
    }
    .wagering-progress-bar { margin-top:8px; height:8px; border-radius:999px; background:var(--border-soft); overflow:hidden; }
    .wagering-progress-fill { height:100%; border-radius:999px; background:var(--accent, #6c5ce7); }
    .user-stat-card small { display:block; margin-top:6px; color:var(--t-light); font-size:11px; font-weight:600; }
</style>

<div class="user-detail-page">
    <div class="user-detail-top">
    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Profil</span>
                <h2 class="card-title">Kullanıcı bilgileri</h2>
            </div>
        </div>
        <div class="admin-stack">
            <div class="data-cell-user">
                <div class="av ma-1"><?= $text(strtoupper(substr($username, 0, 2))) ?></div>
                <div class="data-cell-user-meta">
                    <div class="data-cell-user-name"><?= $text(trim((string) ($user['name'] ?? '') . ' ' . (string) ($user['surname'] ?? ''))) ?></div>
                    <div class="data-cell-user-email"><?= $text($user['email'] ?? '') ?></div>
                </div>
            </div>
            <span class="badge <?= $text($badgeClass($user['is_verified'] ?? 0)) ?>">Verified: <?= $text($user['is_verified'] ?? 0) ?></span>
            <span class="badge <?= $text($badgeClass(((string) ($user['banned'] ?? '0') === '1') ? 'banned' : 'active')) ?>">Durum: <?= ((string) ($user['banned'] ?? '0') === '1') ? 'Banned' : 'Active' ?></span>
            <div class="user-profile-row"><div class="user-profile-label">Telefon</div><div class="user-profile-value"><?= $text($user['phone'] ?? '-') ?></div></div>
            <div class="user-profile-row"><div class="user-profile-label">Kayıt tarihi</div><div class="user-profile-value"><?= $text($user['created_at'] ?? '-') ?></div></div>
            <div class="user-profile-row"><div class="user-profile-label">Ülke / şehir</div><div class="user-profile-value"><?= $text(trim((string) ($user['country'] ?? '') . ' / ' . (string) ($user['city'] ?? ''), ' /')) ?></div></div>
        </div>
    </section>

    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Cüzdan</span>
                <h2 class="card-title">Bakiye ve manuel işlem</h2>
            </div>
        </div>
        <div class="user-stat-grid">
            <div class="user-stat-card"><span>Ana bakiye</span><strong><?= $text($money($user['balance'] ?? 0)) ?></strong></div>
            <div class="user-stat-card"><span>Bonus bakiye</span><strong><?= $text($money($user['bonus_balance'] ?? 0)) ?></strong></div>
            <div class="user-stat-card"><span>Manuel eklenen</span><strong><?= $text($money($summary['manual_add'] ?? 0)) ?></strong></div>
            <div class="user-stat-card"><span>Manuel çıkarılan</span><strong><?= $text($money($summary['manual_subtract'] ?? 0)) ?></strong></div>
            <div class="user-stat-card">
                <span>Ana bakiye çevrim (1x)</span>
                <strong><?= $text($money($accountWagering['progress'] ?? 0)) ?> / <?= $text($money($accountWagering['required'] ?? 0)) ?></strong>
                <div class="wagering-progress-bar"><div class="wagering-progress-fill" style="width:<?= $text((float) ($accountWagering['percent'] ?? 0)) ?>%"></div></div>
                <small><?= $text(number_format((float) ($accountWagering['percent'] ?? 0), 1)) ?>% tamamlandı · Kalan: <?= $text($money($accountWagering['remaining'] ?? 0)) ?></small>
            </div>
        </div>
        <form method="post" action="<?= htmlspecialchars(AdminAuth::url('/user/balance-adjust'), ENT_QUOTES, 'UTF-8') ?>" class="user-balance-form">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="user_id" value="<?= $text($userId) ?>">
            <div class="field">
                <label class="field-label" for="balanceAdjustWallet">Cüzdan</label>
                <select id="balanceAdjustWallet" class="select" name="wallet">
                    <option value="balance">Ana bakiye</option>
                    <option value="bonus_balance">Bonus bakiye</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="balanceAdjustAction">İşlem</label>
                <select id="balanceAdjustAction" class="select" name="action">
                    <option value="add">Bakiye ekle</option>
                    <option value="subtract">Bakiye çıkar</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="balanceAdjustAmount">Tutar (₺)</label>
                <input id="balanceAdjustAmount" class="input" type="number" name="amount" step="0.01" min="0.01" required>
            </div>
            <div class="field">
                <label class="field-label" for="balanceAdjustNote">Admin notu</label>
                <input id="balanceAdjustNote" class="input" type="text" name="note" maxlength="500" placeholder="İşlem açıklaması">
            </div>
            <div class="form-actions span-2">
                <span class="badge dot warning">Bu işlem kullanıcı bakiyesini doğrudan değiştirir.</span>
                <span class="spacer"></span>
                <button class="btn btn--primary" type="submit">Bakiye işlemini kaydet</button>
            </div>
        </form>
    </section>
    </div>

    <section class="card admin-compact-card user-detail-section">
        <div class="card-head"><div class="card-title-wrap"><span class="eyebrow">Finans</span><h2 class="card-title">Finans özeti</h2></div></div>
        <div class="user-stat-grid">
            <div class="user-stat-card"><span>Onaylı yatırım</span><strong><?= $text($money($summary['deposit_total'] ?? 0)) ?></strong></div>
            <div class="user-stat-card"><span>Bekleyen yatırım</span><strong><?= $text($money($summary['deposit_pending'] ?? 0)) ?></strong></div>
            <div class="user-stat-card"><span>Onaylı çekim</span><strong><?= $text($money($summary['withdraw_total'] ?? 0)) ?></strong></div>
            <div class="user-stat-card"><span>Bekleyen çekim</span><strong><?= $text($money($summary['withdraw_pending'] ?? 0)) ?></strong></div>
        </div>
    </section>

    <?php
    $activeBonuses = array_map(static function (array $row) use ($money): array {
        $target = (float) ($row['wagering_target'] ?? 0);
        $bet = (float) ($row['total_bet_amount'] ?? 0);
        $percent = $target > 0 ? min(100.0, round(($bet / $target) * 100, 1)) : 100.0;
        $row['cevrim_hedef'] = $target > 0 ? $money($target) : '-';
        $row['cevrim_ilerleme'] = $money($bet) . ' (' . number_format($percent, 1) . '%)';
        $row['cevrim_durumu'] = ((int) ($row['is_complete'] ?? 0) === 1) ? 'Tamamlandı' : 'Devam ediyor';
        return $row;
    }, $activeBonuses);

    $sections = [
        ['title' => 'Yatırımlar', 'rows' => $deposits, 'columns' => ['id' => 'ID', 'method' => 'Metot', 'provider' => 'Provider', 'amount' => 'Tutar', 'status' => 'Durum', 'trx' => 'TRX', 'created_at' => 'Tarih']],
        ['title' => 'Çekimler', 'rows' => $withdrawals, 'columns' => ['id' => 'ID', 'method' => 'Metot', 'provider' => 'Provider', 'amount' => 'Tutar', 'status' => 'Durum', 'admin_status' => 'Admin', 'created_at' => 'Tarih']],
        ['title' => 'Admin bakiye işlemleri', 'rows' => $adjustments, 'columns' => ['id' => 'ID', 'wallet' => 'Cüzdan', 'action' => 'İşlem', 'amount' => 'Tutar', 'before_balance' => 'Önce', 'after_balance' => 'Sonra', 'admin_username' => 'Admin', 'created_at' => 'Tarih']],
        ['title' => 'Oyun işlemleri', 'type' => 'games', 'rows' => $games, 'columns' => ['id' => 'ID', 'game_name' => 'Oyun', 'transaction_id' => 'Transaction', 'round_id' => 'Round', 'txn_type' => 'Tip', 'bet_amount' => 'Bet', 'win_amount' => 'Win', 'balance_after' => 'Bakiye', 'created_at' => 'Tarih']],
        ['title' => 'Spor kuponları', 'rows' => $sportsbookCoupons, 'columns' => ['id' => 'ID', 'coupon_id' => 'Kupon', 'transaction_id' => 'Transaction', 'round_id' => 'Round', 'vendor_code' => 'Vendor', 'game_code' => 'Sport', 'txn_type' => 'Kazanç/Kayıp', 'amount' => 'Tutar', 'before_balance' => 'Önce', 'after_balance' => 'Sonra', 'currency' => 'Para', 'match_result' => 'Maç Sonucu', 'processed_coupon' => 'İşlenmiş Kupon', 'status' => 'Durum', 'created_at' => 'Tarih']],
        ['title' => 'Bonus talepleri', 'rows' => $bonusClaims, 'columns' => ['id' => 'ID', 'bonus_name' => 'Bonus', 'requested_amount' => 'Tutar', 'status' => 'Durum', 'processed_by' => 'İşleyen', 'processed_at' => 'İşlem tarihi', 'created_at' => 'Tarih']],
        ['title' => 'Aktif bonuslar', 'rows' => $activeBonuses, 'columns' => ['id' => 'ID', 'name' => 'Bonus', 'initial_amount' => 'İlk tutar', 'current_bonus_balance' => 'Mevcut', 'cevrim_hedef' => 'Çevrim hedefi', 'cevrim_ilerleme' => 'Çevrim ilerleme', 'cevrim_durumu' => 'Çevrim durumu', 'status' => 'Durum', 'deadline' => 'Deadline', 'created_at' => 'Tarih']],
    ];
    ?>
    <?php foreach ($sections as $section): ?>
        <section class="card admin-compact-card user-detail-section">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Üye Hareketleri</span>
                    <h2 class="card-title"><?= $text($section['title']) ?></h2>
                </div>
            </div>
            <div class="admin-compact-table-wrap">
                <table class="admin-compact-table">
                    <thead><tr><?php foreach ($section['columns'] as $label): ?><th><?= $text($label) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php if (($section['type'] ?? '') === 'games'): ?>
                        <?php if ($section['rows'] === []): ?>
                            <tr><td colspan="<?= count($section['columns']) ?>">Kayıt bulunamadı.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($section['rows'] as $row): ?>
                            <tr>
                                <?php foreach ($section['columns'] as $column => $label): ?>
                                    <td style="overflow-wrap:anywhere">
                                        <?php if ($column === 'game_name'): ?>
                                            <?php $img = trim((string) ($row['image_url'] ?? '')); ?>
                                            <div class="user-game-cell">
                                                <?php if ($img !== ''): ?><img class="user-game-thumb" src="<?= $text($img) ?>" alt="<?= $text($row['game_name'] ?? 'Oyun') ?>" loading="lazy"><?php endif; ?>
                                                <div class="user-game-meta">
                                                    <div class="user-game-name"><?= $text($row['game_name'] ?? '-') ?></div>
                                                    <div class="user-game-provider"><?= $text($row['provider_name'] ?? '') ?></div>
                                                </div>
                                            </div>
                                        <?php elseif (preg_match('/amount|balance|fee/i', (string) $column) === 1): ?>
                                            <span class="data-cell-mono"><?= $text($money($row[$column] ?? 0)) ?></span>
                                        <?php elseif ($column === 'txn_type'): ?>
                                            <?php $txnLabel = $txnTypeLabel($row[$column] ?? ''); ?>
                                            <span class="badge <?= $text($badgeClass($row[$column] ?? '')) ?>"><?= $text($txnLabel) ?></span>
                                        <?php elseif (preg_match('/status|action/i', (string) $column) === 1): ?>
                                            <span class="badge <?= $text($badgeClass($row[$column] ?? '')) ?>"><?= $text($row[$column] ?? '') ?></span>
                                        <?php else: ?>
                                            <?= $text($row[$column] ?? '') ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php $renderRows($section['rows'], $section['columns']); ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="card admin-compact-card user-detail-section">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Admin Notları</span>
                <h2 class="card-title">Kullanıcı notları</h2>
            </div>
        </div>
        <form method="post" action="<?= htmlspecialchars(AdminAuth::url('/user/note/store'), ENT_QUOTES, 'UTF-8') ?>" style="display:flex;gap:10px;align-items:flex-end;padding:0 0 14px">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="user_id" value="<?= $text($userId) ?>">
            <div class="field" style="flex:1">
                <label class="field-label" for="noteContent">Yeni not ekle</label>
                <input id="noteContent" class="input" type="text" name="content" maxlength="2000" placeholder="Not içeriği..." required>
            </div>
            <button class="btn btn--primary" type="submit" style="white-space:nowrap">Not kaydet</button>
        </form>
        <div class="admin-compact-table-wrap">
            <table class="admin-compact-table">
                <thead><tr><th>ID</th><th>İçerik</th><th>Admin</th><th>Tarih</th></tr></thead>
                <tbody>
                <?php if ($notes === []): ?>
                    <tr><td colspan="4">Not bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                        <tr>
                            <td><?= $text($note['id'] ?? '') ?></td>
                            <td style="overflow-wrap:anywhere;max-width:600px;white-space:pre-wrap"><?= $text($note['content'] ?? '') ?></td>
                            <td><?= $text($note['created_by'] ?? '-') ?></td>
                            <td><?= $text($note['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card admin-compact-card user-detail-section">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Oturumlar</span>
                <h2 class="card-title">JWT oturumları</h2>
            </div>
        </div>
        <div class="admin-compact-table-wrap">
            <table class="admin-compact-table">
                <thead><tr><th>ID</th><th>IP</th><th>User Agent</th><th>Son görülme</th><th>Bitiş</th><th>İptal</th></tr></thead>
                <tbody>
                <?php if ($sessions === []): ?>
                    <tr><td colspan="6">Oturum bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?= $text($session['id'] ?? '') ?></td>
                            <td><?= $text($session['ip_address'] ?? '-') ?></td>
                            <td style="overflow-wrap:anywhere;max-width:280px;white-space:normal;font-size:11px"><?= $text(substr((string) ($session['user_agent'] ?? ''), 0, 100)) ?></td>
                            <td><?= $text($session['last_seen_at'] ?? '-') ?></td>
                            <td><?= $text($session['expires_at'] ?? '-') ?></td>
                            <td><span class="badge <?= $text($badgeClass($session['revoked_at'] ? 'cancelled' : 'active')) ?>"><?= ($session['revoked_at'] ? 'İptal' : 'Aktif') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</section>
