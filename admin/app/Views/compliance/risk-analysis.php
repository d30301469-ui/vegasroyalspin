<?php

$multiWithdraw = is_array($multiWithdraw ?? null) ? $multiWithdraw : [];
$highDepositors = is_array($highDepositors ?? null) ? $highDepositors : [];
$frozenAccounts = is_array($frozenAccounts ?? null) ? $frozenAccounts : [];
$kycPendingHighBalance = is_array($kycPendingHighBalance ?? null) ? $kycPendingHighBalance : [];
$number = $number ?? static fn ($v): string => number_format((float) $v, 2, ',', '.');
$money = static fn ($v): string => '₺' . number_format((float) $v, 2, ',', '.');
?>
<style>
    .risk-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .risk-card { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card); padding:16px; min-width:0; }
    .risk-card-head { align-items:center; border-bottom:1px solid var(--border-soft); display:flex; gap:10px; justify-content:space-between; margin:0 -16px 12px; padding:0 16px 10px; }
    .risk-card-title { color:var(--t-base); font-size:13px; font-weight:900; margin:0; }
    .risk-card-badge { border-radius:999px; font-size:10px; font-weight:900; padding:4px 10px; }
    .risk-card-badge.danger { background:var(--danger-soft); color:var(--danger); }
    .risk-card-badge.warning { background:var(--warning-soft); color:var(--warning); }
    .risk-card-badge.info { background:var(--info-soft); color:var(--info); }
    .risk-table { border-collapse:collapse; width:100%; }
    .risk-table th { border-bottom:1px solid var(--border); color:var(--t-light); font-size:10px; font-weight:800; padding:7px 8px; text-align:left; text-transform:uppercase; }
    .risk-table td { border-bottom:1px solid var(--border-soft); color:var(--t-muted); font-size:11px; padding:8px; }
    .risk-table tr:last-child td { border-bottom:0; }
    .risk-link { color:var(--primary); font-weight:800; }
    .risk-empty { color:var(--t-muted); font-size:12px; padding:20px; text-align:center; }
    .risk-num { font-family:'JetBrains Mono',monospace; font-size:11px; }
    @media (max-width:1000px) { .risk-grid { grid-template-columns:1fr; } }
</style>

<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Uyum · Risk</span>
        <h1 class="hero-title">Risk <span class="accent">Analizi</span></h1>
        <p class="hero-sub">Çoklu bekleyen çekim, yüksek hacimli yatırım, dondurulmuş hesap ve KYC bekleyen yüksek bakiyeli oyuncu sinyalleri.</p>
    </div>
</div>

<div class="risk-grid">
    <!-- Multi Withdrawals -->
    <div class="risk-card">
        <div class="risk-card-head">
            <h2 class="risk-card-title">Çoklu Bekleyen Çekim</h2>
            <span class="risk-card-badge danger"><?= count($multiWithdraw) ?> oyuncu</span>
        </div>
        <?php if (empty($multiWithdraw)): ?>
            <div class="risk-empty">Riskli çekim sinyali yok.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="risk-table">
            <thead><tr><th>Oyuncu</th><th>Çekim</th><th>Toplam</th></tr></thead>
            <tbody>
            <?php foreach ($multiWithdraw as $r): ?>
                <tr>
                    <td><a class="risk-link" href="<?= htmlspecialchars(AdminAuth::url('/user?id=' . ($r['user_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($r['fullname'] ?? $r['username'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td class="risk-num"><?= (int) ($r['pending_count'] ?? 0) ?> adet</td>
                    <td class="risk-num"><?= htmlspecialchars($money($r['total_amount'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- High Depositors -->
    <div class="risk-card">
        <div class="risk-card-head">
            <h2 class="risk-card-title">Yüksek Hacimli Yatırımcılar</h2>
            <span class="risk-card-badge info"><?= count($highDepositors) ?> oyuncu</span>
        </div>
        <?php if (empty($highDepositors)): ?>
            <div class="risk-empty">Henüz yatırım verisi yok.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="risk-table">
            <thead><tr><th>Oyuncu</th><th>İşlem</th><th>Toplam</th><th>Max Tek</th></tr></thead>
            <tbody>
            <?php foreach ($highDepositors as $r): ?>
                <tr>
                    <td><a class="risk-link" href="<?= htmlspecialchars(AdminAuth::url('/user?id=' . ($r['user_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($r['fullname'] ?? $r['username'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td class="risk-num"><?= (int) ($r['tx_count'] ?? 0) ?></td>
                    <td class="risk-num"><?= htmlspecialchars($money($r['total_deposited'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="risk-num"><?= htmlspecialchars($money($r['max_single'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Frozen Accounts -->
    <div class="risk-card">
        <div class="risk-card-head">
            <h2 class="risk-card-title">Dondurulmuş Hesaplar</h2>
            <span class="risk-card-badge danger"><?= count($frozenAccounts) ?> hesap</span>
        </div>
        <?php if (empty($frozenAccounts)): ?>
            <div class="risk-empty">Dondurulmuş hesap yok.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="risk-table">
            <thead><tr><th>Oyuncu</th><th>Bakiye</th><th>Bonus</th><th>Tarih</th></tr></thead>
            <tbody>
            <?php foreach ($frozenAccounts as $r): ?>
                <tr>
                    <td><a class="risk-link" href="<?= htmlspecialchars(AdminAuth::url('/user?id=' . ($r['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(trim((string) ($r['name'] ?? '') . ' ' . (string) ($r['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?: htmlspecialchars((string) ($r['username'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td class="risk-num"><?= htmlspecialchars($money($r['balance'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="risk-num"><?= htmlspecialchars($money($r['bonus_balance'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(date('d.m.Y', strtotime((string) ($r['updated_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- KYC Pending High Balance -->
    <div class="risk-card">
        <div class="risk-card-head">
            <h2 class="risk-card-title">KYC Bekleyen Yüksek Bakiye</h2>
            <span class="risk-card-badge warning"><?= count($kycPendingHighBalance) ?> oyuncu</span>
        </div>
        <?php if (empty($kycPendingHighBalance)): ?>
            <div class="risk-empty">KYC bekleyen yüksek bakiyeli oyuncu yok.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="risk-table">
            <thead><tr><th>Oyuncu</th><th>Bakiye</th><th>KYC Tarihi</th></tr></thead>
            <tbody>
            <?php foreach ($kycPendingHighBalance as $r): ?>
                <tr>
                    <td><a class="risk-link" href="<?= htmlspecialchars(AdminAuth::url('/user?id=' . ($r['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(trim((string) ($r['name'] ?? '') . ' ' . (string) ($r['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?: htmlspecialchars((string) ($r['username'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td class="risk-num"><?= htmlspecialchars($money($r['balance'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(date('d.m.Y', strtotime((string) ($r['submitted_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
</section>
