<?php
$affiliate = $affiliate ?? [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');
$flash = (string) ($flash ?? '');
$referredUsers = is_array($referredUsers ?? null) ? $referredUsers : [];
$recentCommissions = is_array($recentCommissions ?? null) ? $recentCommissions : [];
$payouts = is_array($payouts ?? null) ? $payouts : [];
$commissionSummary = is_array($commissionSummary ?? null) ? $commissionSummary : [];
$clickStats = is_array($clickStats ?? null) ? $clickStats : [];
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
?>
<section class="admin-surface">

<!-- Hero -->
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Ortaklık Sistemi · Detay · #<?= (int) $affiliate['id'] ?></span>
        <h1 class="hero-title"><?= $text($affiliate['full_name'] ?: $affiliate['email']) ?></h1>
        <p class="hero-sub">Referans kodu: <strong><?= $text($affiliate['referral_code']) ?></strong>
            · Plan: <strong><?= $text($affiliate['plan_name'] ?? 'Atanmamış') ?></strong>
            · Durum: <span class="badge <?= $badgeClass($affiliate['status'] ?? '') ?>"><?= $statusLabel($affiliate['status'] ?? '') ?></span></p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= AdminAuth::url('/affiliates') ?>">← Ortaklar</a>
    </div>
</div>

<?php if ($flash !== ''): ?>
<div class="alert is-success"><?= $text($flash) ?></div>
<?php endif; ?>

<style>
.aff-detail-page { display:flex; flex-direction:column; gap:18px; }
.aff-detail-top { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
.aff-stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
.aff-stat-card { border:1px solid var(--border-soft); border-radius:16px; background:var(--bg-muted); padding:14px; }
.aff-stat-card span { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--t-light); margin-bottom:4px; }
.aff-stat-card strong { font-size:18px; color:var(--t-base); }
.aff-profile-stack { display:flex; flex-direction:column; gap:6px; }
.aff-profile-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border-soft); }
.aff-profile-row:last-child { border-bottom:none; }
.aff-profile-label { font-size:12px; color:var(--t-light); font-weight:600; }
.aff-profile-value { font-size:13px; color:var(--t-base); text-align:right; }
.aff-detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media (max-width:900px) { .aff-detail-top, .aff-detail-grid { grid-template-columns:1fr; } .aff-stat-grid { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="aff-detail-page">

<!-- Stats -->
<div class="aff-stat-grid">
    <div class="aff-stat-card"><span>Toplam Kazanç</span><strong><?= $money($affiliate['total_earned']) ?> ₺</strong></div>
    <div class="aff-stat-card"><span>Ödenen</span><strong><?= $money($affiliate['total_paid']) ?> ₺</strong></div>
    <div class="aff-stat-card"><span>Güncel Bakiye</span><strong><?= $money($affiliate['balance']) ?> ₺</strong></div>
    <div class="aff-stat-card"><span>Referans Kodu</span><strong style="font-family:monospace"><?= $text($affiliate['referral_code']) ?></strong></div>
    <div class="aff-stat-card"><span>Yönlendirilen</span><strong><?= count($referredUsers) ?></strong></div>
    <div class="aff-stat-card"><span>Tıklanma</span><strong><?= (int) ($clickStats['clicks'] ?? 0) ?></strong></div>
    <div class="aff-stat-card"><span>Dönüşüm</span><strong><?= (int) ($clickStats['conversions'] ?? 0) ?></strong></div>
    <div class="aff-stat-card"><span>Plan</span><strong><?= $text($affiliate['plan_name'] ?? 'Atanmamış') ?></strong></div>
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
                <h2 class="card-title">Yönlendirilen oyuncular (<?= count($referredUsers) ?>)</h2>
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
