<?php

$multiWithdraw = is_array($multiWithdraw ?? null) ? $multiWithdraw : [];
$riskHoldWithdrawals = is_array($riskHoldWithdrawals ?? null) ? $riskHoldWithdrawals : [];
$highDepositors = is_array($highDepositors ?? null) ? $highDepositors : [];
$frozenAccounts = is_array($frozenAccounts ?? null) ? $frozenAccounts : [];
$kycPendingHighBalance = is_array($kycPendingHighBalance ?? null) ? $kycPendingHighBalance : [];
$chartData = is_array($chartData ?? null) ? $chartData : [];
$number = $number ?? static fn ($v): string => number_format((float) $v, 2, ',', '.');
$money = static fn ($v): string => '₺' . number_format((float) $v, 2, ',', '.');
$amlOpen = is_array($chartData['aml_open'] ?? null) ? $chartData['aml_open'] : [];
$riskOpen = is_array($chartData['risk_open'] ?? null) ? $chartData['risk_open'] : [];
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$memberLabel = static function (array $r): string {
    $name = trim((string) ($r['member_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $full = trim((string) ($r['name'] ?? '') . ' ' . (string) ($r['surname'] ?? ''));
    if ($full !== '') {
        return $full;
    }
    return (string) ($r['fullname'] ?? $r['username'] ?? '-');
};
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
    .risk-kpi { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
    .risk-kpi-card { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; padding:14px; box-shadow:var(--shadow-card); }
    .risk-kpi-card .muted { font-size:11px; margin-bottom:4px; }
    .risk-kpi-card strong { font-size:20px; }
    @media (max-width:1000px) {
        .risk-grid, .risk-kpi { grid-template-columns:1fr 1fr; }
    }
    @media (max-width:720px) {
        .risk-grid, .risk-kpi { grid-template-columns:1fr; }
    }
</style>

<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Uyum · Risk</span>
        <h1 class="hero-title">Risk <span class="accent">Analizi</span></h1>
        <p class="hero-sub">Canlı sinyal paneli, risk skorları ve AML/Risk trend grafikleri.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/compliance/aml-alerts')) ?>">AML Uyarıları</a>
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/compliance/risk-alerts')) ?>">Risk Uyarıları</a>
    </div>
</div>

<div class="risk-kpi">
    <div class="risk-kpi-card"><div class="muted">Açık AML</div><strong><?= (int) ($amlOpen['open'] ?? 0) ?></strong></div>
    <div class="risk-kpi-card"><div class="muted">AML Critical</div><strong><?= (int) ($amlOpen['critical'] ?? 0) ?></strong></div>
    <div class="risk-kpi-card"><div class="muted">Açık Risk</div><strong><?= (int) ($riskOpen['open'] ?? 0) ?></strong></div>
    <div class="risk-kpi-card"><div class="muted">Risk Critical</div><strong><?= (int) ($riskOpen['critical'] ?? 0) ?></strong></div>
</div>

<div class="cmp-charts">
    <div class="cmp-chart-card">
        <div class="cmp-chart-head"><h3 class="cmp-chart-title">Operasyon sinyalleri</h3></div>
        <div class="cmp-chart-wrap"><canvas id="analysis-signals"></canvas></div>
    </div>
    <div class="cmp-chart-card">
        <div class="cmp-chart-head"><h3 class="cmp-chart-title">Üye risk skor seviyeleri</h3></div>
        <div class="cmp-chart-wrap"><canvas id="analysis-levels"></canvas></div>
    </div>
    <div class="cmp-chart-card wide">
        <div class="cmp-chart-head"><h3 class="cmp-chart-title">Çoklu bekleyen çekim (tutar)</h3></div>
        <div class="cmp-chart-wrap"><canvas id="analysis-withdraw"></canvas></div>
    </div>
    <div class="cmp-chart-card wide">
        <div class="cmp-chart-head"><h3 class="cmp-chart-title">Yüksek hacimli yatırımlar</h3></div>
        <div class="cmp-chart-wrap"><canvas id="analysis-deposit"></canvas></div>
    </div>
    <div class="cmp-chart-card wide">
        <div class="cmp-chart-head"><h3 class="cmp-chart-title">AML 14 günlük trend</h3></div>
        <div class="cmp-chart-wrap tall"><canvas id="analysis-aml-trend"></canvas></div>
    </div>
    <div class="cmp-chart-card wide">
        <div class="cmp-chart-head"><h3 class="cmp-chart-title">Risk 14 günlük trend</h3></div>
        <div class="cmp-chart-wrap tall"><canvas id="analysis-risk-trend"></canvas></div>
    </div>
</div>
<?php
$chartPrefix = 'analysis';
require __DIR__ . '/_charts-boot.php';
?>

<div class="risk-grid">
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
                    <td><a class="risk-link" href="<?= $text(AdminAuth::url('/user?id=' . ($r['user_id'] ?? 0))) ?>"><?= $text($memberLabel($r)) ?></a></td>
                    <td class="risk-num"><?= (int) ($r['pending_count'] ?? 0) ?> adet</td>
                    <td class="risk-num"><?= $text($money($r['total_amount'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="risk-card">
        <div class="risk-card-head">
            <h2 class="risk-card-title">Risk Tutma (&gt;10.000₺)</h2>
            <span class="risk-card-badge danger"><?= count($riskHoldWithdrawals) ?> çekim</span>
        </div>
        <?php if (empty($riskHoldWithdrawals)): ?>
            <div class="risk-empty">Risk tutmasındaki çekim yok.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="risk-table">
            <thead><tr><th>Oyuncu</th><th>Tutar</th><th>TRX</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($riskHoldWithdrawals as $r): ?>
                <tr>
                    <td><a class="risk-link" href="<?= $text(AdminAuth::url('/user?id=' . ($r['user_id'] ?? 0))) ?>"><?= $text($memberLabel($r)) ?></a></td>
                    <td class="risk-num"><?= $text($money($r['amount'] ?? 0)) ?></td>
                    <td class="risk-num"><?= $text((string) ($r['trx'] ?? '')) ?></td>
                    <td>
                        <?php if (AdminAuth::canReleaseRiskHoldWithdraw()): ?>
                        <form method="post" action="<?= $text(AdminAuth::url('/megapayz/withdraw/risk-release')) ?>" data-admin-confirm="Finans/MegaPayz’e iletilsin mi?" style="display:inline">
                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $text((string) ($r['id'] ?? '')) ?>">
                            <button class="btn btn--ghost" type="submit" style="font-size:11px;padding:4px 8px">Finansa ilet</button>
                        </form>
                        <?php endif; ?>
                        <?php if (AdminAuth::can('withdrawals') || AdminAuth::can('compliance-risk')): ?>
                        <form method="post" action="<?= $text(AdminAuth::url('/megapayz/withdraw/risk-reject')) ?>" data-admin-confirm="Reddedilip bakiye iade edilsin mi?" style="display:inline">
                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $text((string) ($r['id'] ?? '')) ?>">
                            <button class="btn btn--ghost" type="submit" style="font-size:11px;padding:4px 8px">Red</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

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
                    <td><a class="risk-link" href="<?= $text(AdminAuth::url('/user?id=' . ($r['user_id'] ?? 0))) ?>"><?= $text($memberLabel($r)) ?></a></td>
                    <td class="risk-num"><?= (int) ($r['tx_count'] ?? 0) ?></td>
                    <td class="risk-num"><?= $text($money($r['total_deposited'] ?? 0)) ?></td>
                    <td class="risk-num"><?= $text($money($r['max_single'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

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
            <thead><tr><th>Oyuncu</th><th>Bakiye</th><th>Bonus</th><th>Tarih</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($frozenAccounts as $r): ?>
                <tr>
                    <td><a class="risk-link" href="<?= $text(AdminAuth::url('/user?id=' . ($r['id'] ?? 0))) ?>"><?= $text($memberLabel($r)) ?></a></td>
                    <td class="risk-num"><?= $text($money($r['balance'] ?? 0)) ?></td>
                    <td class="risk-num"><?= $text($money($r['bonus_balance'] ?? 0)) ?></td>
                    <td><?= $text(date('d.m.Y', strtotime((string) ($r['updated_at'] ?? 'now')))) ?></td>
                    <td>
                        <form method="post" action="<?= $text(AdminAuth::url('/user/unfreeze')) ?>" data-admin-confirm="Bu hesabın dondurması kaldırılsın mı?" style="display:inline">
                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                            <input type="hidden" name="user_id" value="<?= $text((string) ($r['id'] ?? '')) ?>">
                            <input type="hidden" name="redirect" value="risk">
                            <button class="btn btn--ghost" type="submit" style="font-size:11px;padding:4px 8px">Çöz</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

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
                    <td><a class="risk-link" href="<?= $text(AdminAuth::url('/user?id=' . ($r['id'] ?? 0))) ?>"><?= $text($memberLabel($r)) ?></a></td>
                    <td class="risk-num"><?= $text($money($r['balance'] ?? 0)) ?></td>
                    <td><?= $text(date('d.m.Y', strtotime((string) ($r['submitted_at'] ?? 'now')))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
</section>
