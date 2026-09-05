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
$freespins = is_array($freespins ?? null) ? $freespins : [];
$accountWagering = is_array($accountWagering ?? null) ? $accountWagering : [];
$activeWalletMode = (string) ($activeWalletMode ?? 'main') === 'bonus' ? 'bonus' : 'main';
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
        in_array($value, ['active', 'confirmed', 'approved', 'success', '1', 'completed', 'win', 'kazanç', 'bahis'], true) => 'success dot',
        in_array($value, ['pending', 'waiting', 'waiting_approval', 'draft'], true) => 'warning dot',
        in_array($value, ['rejected', 'inactive', 'failed', 'cancelled', 'banned', '0', 'kayıp'], true) => 'danger dot',
        in_array($value, ['bet', 'cancel', 'rollback', 'refund', 'iptal', 'iade'], true) => 'warning dot',
        default => 'primary',
    };
};
$txnTypeLabel = static function (mixed $value, bool $sportsbook = false): string {
    $value = strtolower(trim((string) $value));
    if ($sportsbook) {
        return match ($value) {
            'bet', 'promo_bet' => 'Bahis',
            'win', 'promo_win', 'freespins_win' => 'Kazanç',
            'cancel', 'rollback', 'refund' => 'İade',
            default => $value !== '' ? ucfirst($value) : '-',
        };
    }
    return match ($value) {
        'bet', 'promo_bet' => 'Kayıp',
        'win', 'promo_win', 'freespins_win' => 'Kazanç',
        'cancel', 'rollback', 'refund' => 'İptal',
        default => $value !== '' ? ucfirst($value) : '-',
    };
};
$sportsStatusLabel = static function (mixed $value): string {
    $value = strtolower(trim((string) $value));
    return match ($value) {
        'active' => 'Aktif',
        'completed' => 'Tamamlandı',
        'cancelled', 'canceled', 'cancel' => 'İade',
        default => $value !== '' ? $value : '-',
    };
};
$renderRows = static function (array $rows, array $columns, bool $sportsbook = false) use ($text, $money, $badgeClass, $txnTypeLabel, $sportsStatusLabel): void {
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
            } elseif ($column === 'txn_type') {
                $txnLabel = $txnTypeLabel($value, $sportsbook);
                echo '<span class="badge ' . $text($badgeClass($sportsbook ? $txnLabel : $value)) . '">' . $text($txnLabel) . '</span>';
            } elseif ($column === 'status' && $sportsbook) {
                $statusLabel = $sportsStatusLabel($value);
                echo '<span class="badge ' . $text($badgeClass($value)) . '">' . $text($statusLabel) . '</span>';
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
    .wagering-progress-fill--bonus { background:#0d9488; }
    .user-stat-card small { display:block; margin-top:6px; color:var(--t-light); font-size:11px; font-weight:600; }
    .wagering-section-intro { margin:0 0 14px; color:var(--t-light); font-size:13px; line-height:1.45; max-width:72ch; }
    .wagering-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:14px; }
    .wagering-panel { border:1px solid var(--border-soft); border-radius:16px; background:var(--bg-muted); padding:16px; min-width:0; display:flex; flex-direction:column; gap:12px; }
    .wagering-panel-head { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
    .wagering-panel-head h3 { margin:0; font-size:15px; font-weight:700; color:var(--t-base); }
    .wagering-panel-head .badge { margin:0; }
    .wagering-metrics { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; }
    .wagering-metric { min-width:0; }
    .wagering-metric span { display:block; color:var(--t-light); font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; margin-bottom:4px; }
    .wagering-metric strong { display:block; color:var(--t-base); font-size:16px; line-height:1.25; overflow-wrap:anywhere; }
    .wagering-panel-note { margin:0; color:var(--t-light); font-size:12px; line-height:1.45; }
    .wagering-bonus-list { display:flex; flex-direction:column; gap:12px; }
    .wagering-bonus-item { border:1px solid var(--border); border-radius:12px; background:var(--bg-base, #fff); padding:12px; }
    .wagering-bonus-item-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px; }
    .wagering-bonus-item-head strong { color:var(--t-base); font-size:14px; }
    .wagering-empty { margin:0; color:var(--t-light); font-size:13px; }
    @media (max-width:900px) {
        .wagering-grid { grid-template-columns:1fr; }
        .wagering-metrics { grid-template-columns:1fr; }
    }
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
            <span class="badge <?= $text($badgeClass(((string) ($user['banned'] ?? '0') === '1') ? 'banned' : 'active')) ?>">Durum: <?= ((string) ($user['banned'] ?? '0') === '1') ? 'Banlı' : 'Aktif' ?></span>
            <?php if ((string) ($user['banned'] ?? '0') === '1'): ?>
                <form method="post" action="<?= $text(AdminAuth::url('/user/unban')) ?>" data-admin-confirm="Bu kullanıcının banı kaldırılsın mı?" style="margin-top:8px">
                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                    <input type="hidden" name="user_id" value="<?= $text((string) ($user['id'] ?? '')) ?>">
                    <button class="btn btn--primary" type="submit">Banı kaldır</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= $text(AdminAuth::url('/user/ban')) ?>" data-admin-confirm="Bu kullanıcı banlansın mı? Giriş yapamayacak ve aktif oturumları kapanacak." style="margin-top:8px">
                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                    <input type="hidden" name="user_id" value="<?= $text((string) ($user['id'] ?? '')) ?>">
                    <button class="btn btn--ghost" type="submit" style="border-color:#ef4444;color:#ef4444">Kullanıcıyı banla</button>
                </form>
            <?php endif; ?>
            <?php $accountFreeze = is_array($accountFreeze ?? null) ? $accountFreeze : null; ?>
            <?php if ($accountFreeze !== null): ?>
                <span class="badge danger dot">Dondurulmuş<?= !empty($accountFreeze['frozen_at']) ? ' · ' . $text(date('d.m.Y H:i', strtotime((string) $accountFreeze['frozen_at']))) : '' ?></span>
                <?php if (!empty($accountFreeze['reason'])): ?>
                    <div class="user-profile-row"><div class="user-profile-label">Dondurma nedeni</div><div class="user-profile-value"><?= $text($accountFreeze['reason']) ?></div></div>
                <?php endif; ?>
                <form method="post" action="<?= $text(AdminAuth::url('/user/unfreeze')) ?>" data-admin-confirm="Bu hesabın dondurması kaldırılsın mı?" style="margin-top:8px">
                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                    <input type="hidden" name="user_id" value="<?= $text((string) ($user['id'] ?? '')) ?>">
                    <input type="hidden" name="redirect" value="user">
                    <button class="btn btn--primary" type="submit">Dondurmayı kaldır</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= $text(AdminAuth::url('/user/freeze')) ?>" data-admin-confirm="Bu hesap dondurulsun mu?" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                    <input type="hidden" name="user_id" value="<?= $text((string) ($user['id'] ?? '')) ?>">
                    <input class="input" type="text" name="reason" placeholder="Dondurma nedeni" style="min-width:180px">
                    <button class="btn btn--ghost" type="submit">Hesabı dondur</button>
                </form>
            <?php endif; ?>
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
                <span>Aktif oynama cüzdanı</span>
                <strong><?= $text($activeWalletMode === 'bonus' ? 'Bonus bakiye' : 'Ana bakiye') ?></strong>
                <small>Son oyun başlatmada seçilen cüzdan. Bonus çevrimi yalnızca bonus bakiyesiyle oynanınca ilerler.</small>
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

    <?php
    $mainRequired = (float) ($accountWagering['required'] ?? 0);
    $mainProgress = (float) ($accountWagering['progress'] ?? 0);
    $mainRemaining = (float) ($accountWagering['remaining'] ?? 0);
    $mainPercent = (float) ($accountWagering['percent'] ?? 0);
    $mainComplete = !empty($accountWagering['isComplete']);
    $bonusWageringRows = [];
    foreach ($activeBonuses as $bonusRow) {
        $target = (float) ($bonusRow['wagering_target'] ?? 0);
        $bet = (float) ($bonusRow['total_bet_amount'] ?? 0);
        $initial = (float) ($bonusRow['initial_amount'] ?? 0);
        $reqMult = (float) ($bonusRow['wagering_requirement'] ?? 0);
        $percent = $target > 0 ? min(100.0, round(($bet / $target) * 100, 1)) : 100.0;
        $complete = ((int) ($bonusRow['is_complete'] ?? 0) === 1) || ($target > 0 && $bet >= $target);
        $multiplierLabel = '-';
        if ($reqMult > 0) {
            $multiplierLabel = rtrim(rtrim(number_format($reqMult, 2, ',', ''), '0'), ',') . 'x';
        } elseif ($initial > 0 && $target > 0) {
            $multiplierLabel = rtrim(rtrim(number_format($target / $initial, 2, ',', ''), '0'), ',') . 'x';
        }
        $bonusWageringRows[] = [
            'name' => (string) ($bonusRow['name'] ?? 'Bonus'),
            'target' => $target,
            'progress' => $bet,
            'remaining' => max(0.0, round($target - $bet, 2)),
            'percent' => $percent,
            'complete' => $complete,
            'multiplier' => $multiplierLabel,
            'balance' => (float) ($bonusRow['current_bonus_balance'] ?? 0),
            'status' => (string) ($bonusRow['status'] ?? ''),
        ];
    }
    $openBonusCount = count(array_filter(
        $bonusWageringRows,
        static fn (array $row): bool => !$row['complete'] && strtolower((string) $row['status']) === 'active'
    ));
    ?>
    <section class="card admin-compact-card user-detail-section">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Çevrim</span>
                <h2 class="card-title">Ana para ve bonus çevrimi</h2>
            </div>
        </div>
        <p class="wagering-section-intro">
            İki çevrim birbirinden ayrıdır. Ana para çevrimi yatırımın 1 katıdır ve her gerçek bahisle ilerler.
            Bonus para çevrimi yalnızca kullanıcı <strong>bonus bakiyesi</strong> ile oynarken ilerler.
        </p>
        <div class="wagering-grid">
            <div class="wagering-panel">
                <div class="wagering-panel-head">
                    <h3>Ana para çevrimi</h3>
                    <span class="badge primary">1x yatırım</span>
                    <span class="badge <?= $mainComplete ? 'success' : 'warning' ?> dot"><?= $mainComplete ? 'Tamam' : 'Devam ediyor' ?></span>
                </div>
                <div class="wagering-metrics">
                    <div class="wagering-metric"><span>Hedef</span><strong><?= $text($money($mainRequired)) ?></strong></div>
                    <div class="wagering-metric"><span>Çevrilen</span><strong><?= $text($money($mainProgress)) ?></strong></div>
                    <div class="wagering-metric"><span>Kalan</span><strong><?= $text($money($mainRemaining)) ?></strong></div>
                </div>
                <div class="wagering-progress-bar"><div class="wagering-progress-fill" style="width:<?= $text($mainPercent) ?>%"></div></div>
                <p class="wagering-panel-note">
                    <?= $text(number_format($mainPercent, 1)) ?>% tamamlandı.
                    Onaylanan her yatırım hedefe eklenir. Bahisler (ana veya bonus cüzdanından) ana çevrim ilerlemesini artırır.
                </p>
            </div>
            <div class="wagering-panel">
                <div class="wagering-panel-head">
                    <h3>Bonus para çevrimi</h3>
                    <span class="badge <?= $activeWalletMode === 'bonus' ? 'success' : 'primary' ?> dot">
                        Oynama: <?= $text($activeWalletMode === 'bonus' ? 'Bonus bakiye' : 'Ana bakiye') ?>
                    </span>
                    <span class="badge <?= $openBonusCount > 0 ? 'warning' : 'success' ?> dot">
                        <?= $openBonusCount > 0 ? ($openBonusCount . ' aktif çevrim') : 'Açık bonus çevrimi yok' ?>
                    </span>
                </div>
                <?php if ($bonusWageringRows === []): ?>
                    <p class="wagering-empty">Aktif veya listelenen bonus kaydı yok. Bonus çevrimi yalnızca tanımlı bonus hedefi olduğunda takip edilir.</p>
                <?php else: ?>
                    <div class="wagering-bonus-list">
                        <?php foreach ($bonusWageringRows as $bonusWager): ?>
                            <div class="wagering-bonus-item">
                                <div class="wagering-bonus-item-head">
                                    <strong><?= $text($bonusWager['name']) ?></strong>
                                    <span class="badge <?= $bonusWager['complete'] ? 'success' : (strtolower((string) $bonusWager['status']) === 'expired' ? 'danger' : 'warning') ?> dot">
                                        <?= $bonusWager['complete'] ? 'Tamam' : (strtolower((string) $bonusWager['status']) === 'expired' ? 'Sonlandı' : (strtolower((string) $bonusWager['status']) === 'active' ? 'Devam ediyor' : $bonusWager['status'])) ?>
                                    </span>
                                </div>
                                <div class="wagering-metrics">
                                    <div class="wagering-metric"><span>Çarpan</span><strong><?= $text($bonusWager['multiplier']) ?></strong></div>
                                    <div class="wagering-metric"><span>Hedef</span><strong><?= $text($money($bonusWager['target'])) ?></strong></div>
                                    <div class="wagering-metric"><span>Çevrilen</span><strong><?= $text($money($bonusWager['progress'])) ?></strong></div>
                                    <div class="wagering-metric"><span>Kalan</span><strong><?= $text($money($bonusWager['remaining'])) ?></strong></div>
                                    <div class="wagering-metric"><span>Bonus bakiye</span><strong><?= $text($money($bonusWager['balance'])) ?></strong></div>
                                    <div class="wagering-metric"><span>İlerleme</span><strong><?= $text(number_format((float) $bonusWager['percent'], 1)) ?>%</strong></div>
                                </div>
                                <div class="wagering-progress-bar"><div class="wagering-progress-fill wagering-progress-fill--bonus" style="width:<?= $text((float) $bonusWager['percent']) ?>%"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="wagering-panel-note">
                    Bonus çevrimi, kullanıcının aktif oynama cüzdanı <strong>bonus bakiye</strong> iken yapılan bahislerle artar.
                    Ana bakiye ile oynanan bahisler bonus çevrimine yazılmaz.
                </p>
            </div>
        </div>
    </section>

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
        ['title' => 'Oyun geçmişi', 'type' => 'games', 'rows' => $games, 'columns' => ['id' => 'ID', 'game_name' => 'Oyun', 'transaction_id' => 'Transaction', 'round_id' => 'Round', 'txn_type' => 'Tip', 'bet_amount' => 'Bet', 'win_amount' => 'Win', 'balance_after' => 'Bakiye', 'created_at' => 'Tarih']],
        ['title' => 'Spor kuponları', 'type' => 'sportsbook', 'rows' => $sportsbookCoupons, 'columns' => ['id' => 'ID', 'coupon_id' => 'Kupon', 'transaction_id' => 'Transaction', 'round_id' => 'Round', 'vendor_code' => 'Vendor', 'game_code' => 'Sport', 'txn_type' => 'İşlem Türü', 'amount' => 'Tutar', 'before_balance' => 'Önce', 'after_balance' => 'Sonra', 'currency' => 'Para', 'match_result' => 'Maç Sonucu', 'processed_coupon' => 'İşlenmiş Kupon', 'status' => 'Durum', 'created_at' => 'Tarih']],
        ['title' => 'Bonus talepleri', 'rows' => $bonusClaims, 'columns' => ['id' => 'ID', 'bonus_name' => 'Bonus', 'requested_amount' => 'Tutar', 'status' => 'Durum', 'processed_by' => 'İşleyen', 'processed_at' => 'İşlem tarihi', 'created_at' => 'Tarih']],
        ['title' => 'Aktif bonuslar', 'rows' => $activeBonuses, 'columns' => ['id' => 'ID', 'name' => 'Bonus', 'initial_amount' => 'İlk tutar', 'current_bonus_balance' => 'Mevcut', 'cevrim_hedef' => 'Çevrim hedefi', 'cevrim_ilerleme' => 'Çevrim ilerleme', 'cevrim_durumu' => 'Çevrim durumu', 'status' => 'Durum', 'deadline' => 'Deadline', 'created_at' => 'Tarih']],
        ['title' => 'Freespinler', 'rows' => $freespins, 'columns' => ['provider' => 'Sağlayıcı', 'campaign' => 'Kampanya', 'game' => 'Oyun', 'freespins_total' => 'Verilen', 'freespins_done' => 'Kullanılan', 'win_amount' => 'Kazanç', 'status' => 'Durum', 'valid_until' => 'Son kullanım', 'created_at' => 'Veriliş tarihi']],
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
                    <?php elseif (($section['type'] ?? '') === 'sportsbook'): ?>
                        <?php $renderRows($section['rows'], $section['columns'], true); ?>
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
