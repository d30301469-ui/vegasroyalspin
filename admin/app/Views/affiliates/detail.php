<?php
$affiliate = $affiliate ?? [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');
$flash = (string) ($flash ?? '');
$plans = is_array($plans ?? null) ? $plans : [];
$referredUsers = is_array($referredUsers ?? null) ? $referredUsers : [];
$referredUsersMeta = is_array($referredUsersMeta ?? null) ? $referredUsersMeta : [];
$recentCommissions = is_array($recentCommissions ?? null) ? $recentCommissions : [];
$payouts = is_array($payouts ?? null) ? $payouts : [];
$commissionSummary = is_array($commissionSummary ?? null) ? $commissionSummary : [];
$clickStats = is_array($clickStats ?? null) ? $clickStats : [];
$playerCashflow = is_array($playerCashflow ?? null) ? $playerCashflow : [];
$chartData = is_array($chartData ?? null) ? $chartData : [];
$chartPeriod = (string) ($chartPeriod ?? ($playerCashflow['period'] ?? '30'));
$chartPeriodLabel = (string) ($chartPeriodLabel ?? ($playerCashflow['period_label'] ?? 'Son 30 gün'));
$playerDeposits = (float) ($playerCashflow['deposits'] ?? 0);
$playerWithdrawals = (float) ($playerCashflow['withdrawals'] ?? 0);
$playerNet = round($playerDeposits - $playerWithdrawals, 2);
$affiliateId = (int) ($affiliate['id'] ?? 0);
$referredTotal = (int) ($playerCashflow['referred_total'] ?? ($referredUsersMeta['total'] ?? count($referredUsers)));
$usersPage = max(1, (int) ($referredUsersMeta['page'] ?? 1));
$usersPages = max(1, (int) ($referredUsersMeta['pages'] ?? 1));
$usersPerPage = (int) ($referredUsersMeta['per_page'] ?? 100);
$usersListUrl = static function (int $page, ?int $perPage = null) use ($affiliateId, $chartPeriod, $usersPerPage): string {
    $params = [
        'id' => $affiliateId,
        'period' => $chartPeriod,
        'users_page' => max(1, $page),
        'users_per_page' => $perPage ?? $usersPerPage,
    ];

    return AdminAuth::url('/affiliate/detail?' . http_build_query($params));
};
$currentPlanId = (int) ($affiliate['commission_plan_id'] ?? 0);
$netColor = $playerNet >= 0 ? 'var(--success)' : 'var(--danger)';
$periodUrl = static function (string $p) use ($affiliateId): string {
    return AdminAuth::url('/affiliate/detail?id=' . $affiliateId . '&period=' . rawurlencode($p));
};
$badgeClass = static fn (string $status): string => match ($status) {
    'active', 'approved', 'completed', 'paid' => 'success dot',
    'pending', 'processing' => 'warning dot',
    'rejected', 'cancelled', 'suspended' => 'danger dot',
    default => 'primary dot',
};
$statusLabel = static fn (string $s): string => match ($s) {
    'active' => 'Aktif', 'pending' => 'Bekliyor', 'suspended' => 'Askıda',
    'rejected' => 'Red', 'approved' => 'Onaylı', 'paid' => 'Ödendi',
    'completed' => 'Tamamlandı', 'processing' => 'İşleniyor',
    'cancelled' => 'İptal', default => $s,
};
$kpiIconSvg = static function (string $icon): string {
    $paths = [
        'deposit' => '<path d="M12 3v12m0 0l-4-4m4 4l4-4M5 19h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'withdraw' => '<path d="M12 21V9m0 0l-4 4m4-4l4 4M5 5h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'wallet' => '<path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zm0 5h.01M16 7V5a2 2 0 0 0-2-2H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'bonus' => '<path d="M20 12v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8M12 2v6m0 0l-3-3m3 3l3-3M2 12h20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'players' => '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M11 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm10 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'paid' => '<path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'net' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'clicks' => '<path d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M4 4l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'earnings' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
    ];
    $path = $paths[$icon] ?? $paths['players'];

    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">' . $path . '</svg>';
};
$affKpiCards = [
    [
        'label' => 'Toplam Kazanç',
        'value' => $money($affiliate['total_earned']) . ' ₺',
        'sub' => 'Ömür boyu komisyon',
        'accent' => '#7c3aed',
        'icon' => 'earnings',
        'tone' => false,
    ],
    [
        'label' => 'Ödenen',
        'value' => $money($affiliate['total_paid']) . ' ₺',
        'sub' => 'Tamamlanan ödemeler',
        'accent' => '#16a34a',
        'icon' => 'paid',
        'tone' => true,
    ],
    [
        'label' => 'Güncel Bakiye',
        'value' => $money($affiliate['balance']) . ' ₺',
        'sub' => 'Ödenebilir bakiye',
        'accent' => '#2563eb',
        'icon' => 'wallet',
        'tone' => false,
    ],
    [
        'label' => 'Yönlendirilen Oyuncu',
        'value' => (string) $referredTotal,
        'sub' => 'Kod: ' . (string) ($affiliate['referral_code'] ?? ''),
        'accent' => '#0891b2',
        'icon' => 'players',
        'tone' => false,
    ],
    [
        'label' => 'Oyuncu Yatırımı',
        'value' => $money($playerDeposits) . ' ₺',
        'sub' => (int) ($playerCashflow['deposit_count'] ?? 0) . ' işlem · ' . $chartPeriodLabel,
        'accent' => '#16a34a',
        'icon' => 'deposit',
        'tone' => true,
    ],
    [
        'label' => 'Oyuncu Çekimi',
        'value' => $money($playerWithdrawals) . ' ₺',
        'sub' => (int) ($playerCashflow['withdraw_count'] ?? 0) . ' işlem · ' . $chartPeriodLabel,
        'accent' => '#dc2626',
        'icon' => 'withdraw',
        'tone' => true,
    ],
    [
        'label' => 'Net Nakit',
        'value' => $money($playerNet) . ' ₺',
        'sub' => 'Yatırım − Çekim · ' . $chartPeriodLabel,
        'accent' => $playerNet >= 0 ? '#16a34a' : '#dc2626',
        'icon' => 'net',
        'tone' => true,
    ],
    [
        'label' => 'Tıklanma / Dönüşüm',
        'value' => (int) ($clickStats['clicks'] ?? 0) . ' / ' . (int) ($clickStats['conversions'] ?? 0),
        'sub' => (string) ($affiliate['plan_name'] ?? 'Plansız'),
        'accent' => '#d97706',
        'icon' => 'clicks',
        'tone' => false,
    ],
];
?>
<section class="admin-surface">

<!-- Hero -->
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Ortaklık Sistemi · Detay · #<?= $affiliateId ?></span>
        <h1 class="hero-title"><?= $text($affiliate['full_name'] ?: $affiliate['email']) ?></h1>
        <p class="hero-sub">Referans kodu: <strong><?= $text($affiliate['referral_code']) ?></strong>
            · Plan: <strong><?= $text($affiliate['plan_name'] ?? 'Atanmamış') ?></strong>
            · Durum: <span class="badge <?= $badgeClass($affiliate['status'] ?? '') ?>"><?= $statusLabel($affiliate['status'] ?? '') ?></span>
            · Dönem: <strong><?= $text($chartPeriodLabel) ?></strong></p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= AdminAuth::url('/affiliates') ?>">← Ortaklar</a>
    </div>
</div>

<?php if ($flash !== ''): ?>
<div class="alert is-success"><?= $text($flash) ?></div>
<?php endif; ?>

<style>
.aff-detail-page { display:flex; flex-direction:column; gap:16px; }
.aff-period {
    display:flex; flex-wrap:wrap; gap:8px; align-items:center;
    background:var(--bg-card); border:1px solid var(--border); border-radius:14px;
    box-shadow:var(--shadow-card); padding:10px 12px;
}
.aff-period-label { color:var(--t-muted); font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; margin-right:4px; }
.aff-period a {
    appearance:none; text-decoration:none; border:1px solid var(--border-soft); background:transparent;
    color:var(--t-muted); border-radius:999px; font-size:12px; font-weight:800; padding:7px 12px; transition:.14s ease;
}
.aff-period a:hover { color:var(--t-base); background:var(--bg-muted); }
.aff-period a.is-on { background:var(--primary); border-color:var(--primary); color:#fff; }
.aff-kpi-row { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
.aff-kpi {
    background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card);
    padding:14px 14px 12px; min-width:0; transition:.16s ease; display:flex; flex-direction:column; gap:8px;
}
.aff-kpi:hover { background:color-mix(in srgb, var(--bg-muted) 28%, var(--bg-card)); }
.aff-kpi-top { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
.aff-kpi-icon {
    align-items:center; background:color-mix(in srgb, var(--kpi-accent,#3b82f6) 16%, transparent);
    border-radius:10px; color:var(--kpi-accent,#3b82f6); display:inline-flex; flex-shrink:0; height:34px; justify-content:center; width:34px;
}
.aff-kpi-label {
    color:var(--t-muted); display:block; font-size:11px; font-weight:800; letter-spacing:.04em;
    line-height:1.3; text-transform:uppercase;
}
.aff-kpi-value {
    color:var(--t-base); font-size:20px; font-weight:900;
    letter-spacing:-.02em; line-height:1.15; overflow-wrap:anywhere;
}
.aff-kpi-value.tone { color:var(--kpi-accent,#3b82f6); }
.aff-kpi-meta { color:var(--t-muted); font-size:11px; font-weight:700; }
.aff-charts { display:grid; grid-template-columns:1.4fr 1fr; gap:14px; }
.aff-charts-bottom { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.aff-chart-card {
    background:var(--bg-card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow-card);
    padding:14px 16px 16px; min-width:0;
}
.aff-chart-card.full { grid-column:1 / -1; }
.aff-chart-head { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:12px; }
.aff-chart-title { margin:0; color:var(--t-base); font-size:14px; font-weight:900; letter-spacing:-.02em; }
.aff-chart-meta { color:var(--t-muted); font-size:11px; font-weight:700; white-space:nowrap; }
.aff-chart-wrap { position:relative; height:280px; }
.aff-chart-wrap.tall { height:340px; }
.aff-chart-empty {
    position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    color:var(--t-muted); font-size:12px; font-weight:700; pointer-events:none;
}
.aff-profile-stack { display:flex; flex-direction:column; gap:6px; }
.aff-profile-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border-soft); }
.aff-profile-row:last-child { border-bottom:none; }
.aff-profile-label { font-size:12px; color:var(--t-light); font-weight:600; }
.aff-profile-value { font-size:13px; color:var(--t-base); text-align:right; }
.aff-detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media (max-width:1200px) {
    .aff-kpi-row { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .aff-charts, .aff-charts-bottom { grid-template-columns:1fr; }
}
@media (max-width:900px) {
    .aff-detail-grid { grid-template-columns:1fr; }
}
@media (max-width:640px) {
    .aff-kpi-row { grid-template-columns:1fr; }
    .aff-chart-wrap, .aff-chart-wrap.tall { height:240px; }
}
</style>

<div class="aff-detail-page">

<div class="aff-period" role="tablist" aria-label="Dönem filtresi">
    <span class="aff-period-label">Dönem</span>
    <?php foreach (['7' => '7 gün', '30' => '30 gün', '90' => '90 gün', 'all' => 'Tümü'] as $pKey => $pLabel): ?>
        <a class="<?= $chartPeriod === $pKey ? 'is-on' : '' ?>" href="<?= $text($periodUrl($pKey)) ?>"><?= $text($pLabel) ?></a>
    <?php endforeach; ?>
</div>

<div class="aff-kpi-row">
    <?php foreach ($affKpiCards as $card): ?>
    <div class="aff-kpi" style="--kpi-accent:<?= htmlspecialchars((string) $card['accent'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="aff-kpi-top">
            <span class="aff-kpi-icon"><?= $kpiIconSvg((string) $card['icon']) ?></span>
        </div>
        <span class="aff-kpi-label"><?= $text($card['label']) ?></span>
        <div class="aff-kpi-value <?= !empty($card['tone']) ? 'tone' : '' ?>"><?= $text($card['value']) ?></div>
        <div class="aff-kpi-meta"><?= $text($card['sub']) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="aff-charts">
    <div class="aff-chart-card">
        <div class="aff-chart-head">
            <div>
                <h2 class="aff-chart-title">Oyuncu nakit akışı</h2>
            </div>
            <span class="aff-chart-meta"><?= $text($chartPeriodLabel) ?> · line</span>
        </div>
        <div class="aff-chart-wrap tall">
            <canvas id="aff-trend"></canvas>
            <div class="aff-chart-empty" id="aff-trend-empty" hidden>Bu dönemde yatırım/çekim yok</div>
        </div>
    </div>
    <div class="aff-chart-card">
        <div class="aff-chart-head">
            <div>
                <h2 class="aff-chart-title">Yatırım / çekim dengesi</h2>
            </div>
            <span class="aff-chart-meta"><?= $text($chartPeriodLabel) ?> · doughnut</span>
        </div>
        <div class="aff-chart-wrap">
            <canvas id="aff-share"></canvas>
            <div class="aff-chart-empty" id="aff-share-empty" hidden>Bu dönemde işlem yok</div>
        </div>
    </div>
</div>

<div class="aff-charts-bottom">
    <div class="aff-chart-card">
        <div class="aff-chart-head">
            <div>
                <h2 class="aff-chart-title">Komisyon dağılımı</h2>
            </div>
            <span class="aff-chart-meta">Onaylı + ödenen · bar</span>
        </div>
        <div class="aff-chart-wrap">
            <canvas id="aff-commissions"></canvas>
            <div class="aff-chart-empty" id="aff-commissions-empty" hidden>Komisyon kaydı yok</div>
        </div>
    </div>
    <div class="aff-chart-card">
        <div class="aff-chart-head">
            <div>
                <h2 class="aff-chart-title">Kazanç durumu</h2>
            </div>
            <span class="aff-chart-meta">Ödenen / bakiye · doughnut</span>
        </div>
        <div class="aff-chart-wrap">
            <canvas id="aff-earnings"></canvas>
            <div class="aff-chart-empty" id="aff-earnings-empty" hidden>Kazanç verisi yok</div>
        </div>
    </div>
</div>

<!-- Two Columns -->
<div class="aff-detail-grid">

<!-- LEFT COLUMN -->
<div style="display:flex;flex-direction:column;gap:18px">

    <!-- Profile Edit -->
    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Profil</span>
                <h2 class="card-title">Ortak bilgileri</h2>
            </div>
        </div>
        <form method="post" action="<?= AdminAuth::url('/affiliate/update') ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $affiliate['id'] ?>">
            <div class="user-edit-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px 18px">
                <div class="field">
                    <label class="field-label">Ad Soyad</label>
                    <input class="input" name="full_name" value="<?= $text($affiliate['full_name']) ?>">
                </div>
                <div class="field">
                    <label class="field-label">Firma</label>
                    <input class="input" name="company_name" value="<?= $text($affiliate['company_name']) ?>">
                </div>
                <div class="field">
                    <label class="field-label">E-posta</label>
                    <input class="input" name="email" value="<?= $text($affiliate['email']) ?>">
                </div>
                <div class="field">
                    <label class="field-label">Telefon</label>
                    <input class="input" name="phone" value="<?= $text($affiliate['phone']) ?>">
                </div>
                <div class="field">
                    <label class="field-label">Ülke</label>
                    <input class="input" name="country" value="<?= $text($affiliate['country']) ?>">
                </div>
                <div class="field">
                    <label class="field-label">Şehir</label>
                    <input class="input" name="city" value="<?= $text($affiliate['city']) ?>">
                </div>
                <div class="field">
                    <label class="field-label">Web Sitesi</label>
                    <input class="input" name="website" value="<?= $text($affiliate['website']) ?>">
                </div>
                <div class="field">
                    <label class="field-label">Durum</label>
                    <select class="select" name="status">
                        <option value="pending" <?= $affiliate['status'] === 'pending' ? 'selected' : '' ?>>Onay Bekleyen</option>
                        <option value="active" <?= $affiliate['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="suspended" <?= $affiliate['status'] === 'suspended' ? 'selected' : '' ?>>Askıda</option>
                        <option value="rejected" <?= $affiliate['status'] === 'rejected' ? 'selected' : '' ?>>Reddedildi</option>
                    </select>
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label class="field-label">Komisyon Planı</label>
                    <select class="select" name="commission_plan_id">
                        <option value="0">— Plan atanmamış —</option>
                        <?php foreach ($plans as $plan): ?>
                            <?php
                            $planId = (int) ($plan['id'] ?? 0);
                            $planLabel = (string) ($plan['name'] ?? ('Plan #' . $planId));
                            $planMeta = [];
                            if (($plan['plan_type'] ?? '') !== '') {
                                $planMeta[] = (string) $plan['plan_type'];
                            }
                            if ((float) ($plan['revshare_rate'] ?? 0) > 0) {
                                $planMeta[] = rtrim(rtrim(number_format((float) $plan['revshare_rate'], 2, '.', ''), '0'), '.') . '%';
                            }
                            if ((float) ($plan['cpa_amount'] ?? 0) > 0) {
                                $planMeta[] = number_format((float) $plan['cpa_amount'], 0, ',', '.') . ' ₺ CPA';
                            }
                            if (empty($plan['is_active'])) {
                                $planMeta[] = 'pasif';
                            }
                            if (!empty($plan['is_default'])) {
                                $planMeta[] = 'varsayılan';
                            }
                            if ($planMeta !== []) {
                                $planLabel .= ' (' . implode(' · ', $planMeta) . ')';
                            }
                            ?>
                            <option value="<?= $planId ?>" <?= $currentPlanId === $planId ? 'selected' : '' ?>><?= $text($planLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display:block;margin-top:6px;color:var(--t-light)">Ortağın kazanç hesabı bu plana göre yapılır. Plan oranlarını <a href="<?= AdminAuth::url('/affiliate/plans') ?>">Komisyon Planları</a> sayfasından düzenleyebilirsiniz.</small>
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label class="field-label">Admin Notu</label>
                    <textarea class="input" name="notes" rows="3" style="resize:vertical"><?= $text($affiliate['notes']) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn--primary" type="submit">Bilgileri Kaydet</button>
            </div>
        </form>
    </section>

    <!-- Payment Details -->
    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Ödeme</span>
                <h2 class="card-title">Ödeme bilgileri</h2>
            </div>
        </div>
        <form method="post" action="<?= AdminAuth::url('/affiliate/payment-update') ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $affiliate['id'] ?>">
            <div class="user-edit-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px 18px">
                <div class="field">
                    <label class="field-label">Ödeme Yöntemi</label>
                    <select class="select" name="payment_method">
                        <option value="bank" <?= $affiliate['payment_method'] === 'bank' ? 'selected' : '' ?>>Banka Havalesi</option>
                        <option value="crypto" <?= $affiliate['payment_method'] === 'crypto' ? 'selected' : '' ?>>Kripto</option>
                        <option value="paypal" <?= $affiliate['payment_method'] === 'paypal' ? 'selected' : '' ?>>PayPal</option>
                    </select>
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label class="field-label">Ödeme Detayları (JSON)</label>
                    <textarea class="input" name="payment_details" rows="3" style="resize:vertical;font-family:monospace;font-size:11px"><?= $text($affiliate['payment_details']) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn--primary" type="submit">Ödeme Bilgilerini Kaydet</button>
            </div>
        </form>
    </section>

    <!-- Manual Commission -->
    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Komisyon</span>
                <h2 class="card-title">Manuel komisyon ekle</h2>
            </div>
        </div>
        <form method="post" action="<?= AdminAuth::url('/affiliate/commission-add') ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <input type="hidden" name="affiliate_id" value="<?= $affiliate['id'] ?>">
            <div class="user-edit-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px 18px">
                <div class="field">
                    <label class="field-label">Tutar (₺)</label>
                    <input class="input" type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                <div class="field">
                    <label class="field-label">Oyuncu ID <span style="color:var(--t-muted)">(opsiyonel)</span></label>
                    <input class="input" type="number" name="user_id" min="0" placeholder="0">
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label class="field-label">Açıklama</label>
                    <input class="input" name="description" placeholder="Manuel komisyon" value="Manuel komisyon">
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn--primary" type="submit">Komisyon Ekle</button>
            </div>
        </form>
    </section>

    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Hesaplama</span>
                <h2 class="card-title">Komisyonu yeniden hesapla</h2>
            </div>
        </div>
        <form method="post" action="<?= AdminAuth::url('/affiliate/recalculate') ?>" onsubmit="return confirm('Seçili dönemde yalnızca ödenmemiş otomatik komisyonlar iptal edilip plana göre yeniden yazılır. Ödenmiş (paid) dönemler korunur. Devam?');">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int) $affiliate['id'] ?>">
            <div class="user-edit-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px 18px">
                <div class="field">
                    <label class="field-label">Başlangıç (dahil)</label>
                    <input class="input" type="date" name="date_from" value="<?= $text(date('Y-m-d', strtotime('-1 day'))) ?>" required>
                </div>
                <div class="field">
                    <label class="field-label">Bitiş (hariç)</label>
                    <input class="input" type="date" name="date_to" value="<?= $text(date('Y-m-d')) ?>" required>
                </div>
            </div>
            <p style="margin:8px 0 0;color:var(--t-muted);font-size:12px;font-weight:600;line-height:1.4">
                CPA kayıt gününe değil ilk nitelikli yatırıma (FTD) göre işlenir. RevShare dönem net nakit akışına (yatırım − çekim) uygulanır. Plansız ortaklara varsayılan plan otomatik atanır.
            </p>
            <div class="form-actions">
                <button class="btn btn--ghost" type="submit">Yeniden Hesapla</button>
            </div>
        </form>
    </section>

    <!-- Commission Summary -->
    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Komisyon Özeti</span>
                <h2 class="card-title">Türe göre dağılım</h2>
            </div>
        </div>
        <?php if (empty($commissionSummary)): ?>
        <div style="padding:20px;text-align:center;color:var(--t-muted);font-size:13px">Henüz komisyon kaydı yok.</div>
        <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>Tür</th><th>Adet</th><th>Toplam</th><th>Ödenen</th></tr></thead>
                <tbody>
                    <?php foreach ($commissionSummary as $cs): ?>
                    <tr>
                        <td><span class="badge primary"><?= $text($cs['commission_type']) ?></span></td>
                        <td><?= (int) $cs['cnt'] ?></td>
                        <td class="cell-price pos"><?= $money($cs['total']) ?> ₺</td>
                        <td class="cell-price pos"><?= $money($cs['paid']) ?> ₺</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>

<!-- RIGHT COLUMN -->
<div style="display:flex;flex-direction:column;gap:18px">

    <!-- Referred Users -->
    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Oyuncular</span>
                <h2 class="card-title">Yönlendirilen oyuncular (<?= $referredTotal ?>)</h2>
            </div>
        </div>
        <?php if (empty($referredUsers)): ?>
        <div style="padding:20px;text-align:center;color:var(--t-muted);font-size:13px">Henüz yönlendirilen oyuncu yok.</div>
        <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>ID</th><th>Kullanıcı</th><th>Bakiye</th><th>Kayıt</th></tr></thead>
                <tbody>
                    <?php foreach ($referredUsers as $u): ?>
                    <tr>
                        <td><span class="data-cell-mono">#<?= $u['id'] ?></span></td>
                        <td>
                            <div class="data-cell-user">
                                <div class="av"><?= strtoupper(mb_substr($u['username'] ?? '?', 0, 1)) ?></div>
                                <div class="data-cell-user-meta">
                                    <div class="data-cell-user-name"><?= $text($u['username']) ?></div>
                                    <div class="data-cell-user-email"><?= $text($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="cell-price pos"><?= $money($u['balance']) ?> ₺</td>
                        <td class="cell-date"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;padding:12px 14px;border-top:1px solid var(--border,rgba(255,255,255,.08));font-size:12px;color:var(--t-muted)">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <span><?= (int) $referredTotal ?> oyuncunun tamamı listelenir</span>
                <span>·</span>
                <span>Sayfa <?= (int) $usersPage ?> / <?= (int) $usersPages ?></span>
                <?php foreach ([50, 100, 200, 500] as $size): ?>
                    <a href="<?= $text($usersListUrl(1, $size)) ?>" style="<?= $usersPerPage === $size ? 'font-weight:700;color:var(--t)' : '' ?>"><?= $size ?>/sayfa</a>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:8px">
                <?php if ($usersPage > 1): ?>
                    <a class="btn" href="<?= $text($usersListUrl($usersPage - 1)) ?>">← Önceki</a>
                <?php endif; ?>
                <?php if ($usersPage < $usersPages): ?>
                    <a class="btn" href="<?= $text($usersListUrl($usersPage + 1)) ?>">Sonraki →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- Recent Commissions -->
    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Komisyonlar</span>
                <h2 class="card-title">Son komisyonlar</h2>
            </div>
        </div>
        <?php if (empty($recentCommissions)): ?>
        <div style="padding:20px;text-align:center;color:var(--t-muted);font-size:13px">Henüz komisyon kaydı yok.</div>
        <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Tür</th><th>Tutar</th><th>Oyuncu</th><th>Durum</th></tr></thead>
                <tbody>
                    <?php foreach ($recentCommissions as $c): ?>
                    <tr>
                        <td class="cell-date"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></td>
                        <td><span class="badge primary"><?= $text($c['commission_type']) ?></span></td>
                        <td class="cell-price pos"><?= $money($c['amount']) ?> ₺</td>
                        <td><?= $text($c['user_username'] ?? '-') ?></td>
                        <td><span class="badge <?= $badgeClass($c['status']) ?>"><?= $statusLabel($c['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- Payouts -->
    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Ödemeler</span>
                <h2 class="card-title">Ödeme geçmişi</h2>
            </div>
        </div>
        <?php if (empty($payouts)): ?>
        <div style="padding:20px;text-align:center;color:var(--t-muted);font-size:13px">Henüz ödeme talebi yok.</div>
        <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Tutar</th><th>Yöntem</th><th>Durum</th></tr></thead>
                <tbody>
                    <?php foreach ($payouts as $p): ?>
                    <tr>
                        <td class="cell-date"><?= date('d.m.Y H:i', strtotime($p['requested_at'])) ?></td>
                        <td class="cell-price pos"><?= $money($p['amount']) ?> ₺</td>
                        <td><?= $text($p['method']) ?></td>
                        <td><span class="badge <?= $badgeClass($p['status']) ?>"><?= $statusLabel($p['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>

</div><!-- /aff-detail-grid -->

</div><!-- /aff-detail-page -->

</section>

<script>
(function(){
    var data = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}' ?>;

    var theme = function() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    };
    var labelColor = function() {
        return theme() === 'dark' ? '#94a3b8' : '#64748b';
    };
    var gridColor = function() {
        return theme() === 'dark' ? 'rgba(148,163,184,.12)' : 'rgba(15,23,42,.08)';
    };
    var borderColor = function() {
        return theme() === 'dark' ? '#1e293b' : '#ffffff';
    };
    var legend = function() {
        return {
            display: true,
            position: 'bottom',
            labels: {
                color: labelColor(),
                boxWidth: 8,
                boxHeight: 8,
                padding: 12,
                usePointStyle: true,
                font: { size: 11, weight: '700' }
            }
        };
    };
    var moneyTick = function(value) {
        var n = Number(value || 0);
        if (Math.abs(n) >= 1000000) return '₺' + (n / 1000000).toFixed(1) + 'M';
        if (Math.abs(n) >= 1000) return '₺' + (n / 1000).toFixed(0) + 'K';
        return '₺' + n.toLocaleString('tr-TR', { maximumFractionDigits: 0 });
    };
    var moneyTooltip = function(ctx) {
        var n = Number(ctx.raw || 0);
        return (ctx.dataset.label || '') + ': ₺' + n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    var sumArr = function(arr) {
        return (arr || []).reduce(function(a, b){ return a + Number(b || 0); }, 0);
    };
    var setEmpty = function(id, isEmpty) {
        var el = document.getElementById(id);
        if (!el) return;
        el.hidden = !isEmpty;
        var canvas = el.previousElementSibling;
        if (canvas && canvas.tagName === 'CANVAS') {
            canvas.style.opacity = isEmpty ? '0.18' : '1';
        }
    };
    var destroyIfAny = function(canvas) {
        if (canvas && canvas._chart) {
            canvas._chart.destroy();
            canvas._chart = null;
        }
    };

    var renderTrend = function() {
        var canvas = document.getElementById('aff-trend');
        if (!canvas || typeof Chart !== 'function') return;
        var trend = data.trend || { labels: [], deposits: [], withdrawals: [], net: [] };
        var labels = trend.labels || [];
        var empty = labels.length === 0 || (sumArr(trend.deposits) <= 0 && sumArr(trend.withdrawals) <= 0);
        setEmpty('aff-trend-empty', empty);
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels.length ? labels : ['—'],
                datasets: [
                    {
                        label: 'Yatırım',
                        data: labels.length ? (trend.deposits || []) : [0],
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,.14)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: labels.length > 60 ? 0 : 2,
                        pointHoverRadius: 5,
                        borderWidth: 2.5
                    },
                    {
                        label: 'Çekim',
                        data: labels.length ? (trend.withdrawals || []) : [0],
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220,38,38,.10)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: labels.length > 60 ? 0 : 2,
                        pointHoverRadius: 5,
                        borderWidth: 2.5
                    },
                    {
                        label: 'Net',
                        data: labels.length ? (trend.net || []) : [0],
                        borderColor: '#2563eb',
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.35,
                        pointRadius: labels.length > 60 ? 0 : 2,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                        borderDash: [5, 4]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: legend(),
                    tooltip: { callbacks: { label: moneyTooltip } }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: labelColor(),
                            font: { size: 10, weight: '700' },
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: labels.length > 60 ? 8 : 12
                        }
                    },
                    y: {
                        grid: { color: gridColor(), drawBorder: false },
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' }, callback: moneyTick }
                    }
                }
            }
        });
    };

    var renderShare = function() {
        var canvas = document.getElementById('aff-share');
        if (!canvas || typeof Chart !== 'function') return;
        var share = data.share || {};
        var deposits = Number(share.deposits || 0);
        var withdrawals = Number(share.withdrawals || 0);
        var empty = deposits <= 0 && withdrawals <= 0;
        setEmpty('aff-share-empty', empty);
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: empty ? ['Veri yok'] : ['Yatırım', 'Çekim'],
                datasets: [{
                    data: empty ? [1] : [deposits, withdrawals],
                    backgroundColor: empty
                        ? ['rgba(148,163,184,.35)']
                        : ['rgba(22,163,74,.9)', 'rgba(220,38,38,.9)'],
                    borderColor: borderColor(),
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: legend(),
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                if (empty) return 'Veri yok';
                                var n = Number(ctx.raw || 0);
                                return (ctx.label || '') + ': ₺' + n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                    }
                }
            }
        });
    };

    var renderCommissions = function() {
        var canvas = document.getElementById('aff-commissions');
        if (!canvas || typeof Chart !== 'function') return;
        var pack = data.commissions || { labels: [], values: [], paid: [] };
        var labels = pack.labels || [];
        var values = pack.values || [];
        var paid = pack.paid || [];
        var empty = labels.length === 0 || sumArr(values) <= 0;
        setEmpty('aff-commissions-empty', empty);
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['—'],
                datasets: [
                    {
                        label: 'Toplam',
                        data: labels.length ? values : [0],
                        backgroundColor: 'rgba(37,99,235,.88)',
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 28
                    },
                    {
                        label: 'Ödenen',
                        data: labels.length ? paid : [0],
                        backgroundColor: 'rgba(22,163,74,.88)',
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 28
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: legend(),
                    tooltip: { callbacks: { label: moneyTooltip } }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor(), drawBorder: false },
                        ticks: { color: labelColor(), font: { size: 10, weight: '700' }, callback: moneyTick }
                    }
                }
            }
        });
    };

    var renderEarnings = function() {
        var canvas = document.getElementById('aff-earnings');
        if (!canvas || typeof Chart !== 'function') return;
        var earn = data.earnings || {};
        var paid = Math.max(0, Number(earn.paid || 0));
        var balance = Math.max(0, Number(earn.balance || 0));
        var empty = paid <= 0 && balance <= 0;
        setEmpty('aff-earnings-empty', empty);
        destroyIfAny(canvas);
        canvas._chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: empty ? ['Veri yok'] : ['Ödenen', 'Bakiye'],
                datasets: [{
                    data: empty ? [1] : [paid, balance],
                    backgroundColor: empty
                        ? ['rgba(148,163,184,.35)']
                        : ['rgba(22,163,74,.9)', 'rgba(37,99,235,.9)'],
                    borderColor: borderColor(),
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: legend(),
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                if (empty) return 'Veri yok';
                                var n = Number(ctx.raw || 0);
                                return (ctx.label || '') + ': ₺' + n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                    }
                }
            }
        });
    };

    var init = function() {
        renderTrend();
        renderShare();
        renderCommissions();
        renderEarnings();
    };

    var boot = function(attempt) {
        if (typeof Chart === 'undefined') {
            if (attempt >= 50) return;
            setTimeout(function(){ boot(attempt + 1); }, 100);
            return;
        }
        init();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ boot(0); });
    } else {
        boot(0);
    }
})();
</script>
