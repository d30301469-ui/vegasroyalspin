<?php
$payouts = is_array($payouts ?? null) ? $payouts : [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');
$flash = (string) ($flash ?? '');
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Ortaklık Sistemi</span>
        <h1 class="hero-title">Ödeme <span class="accent">yönetimi</span></h1>
        <p class="hero-sub">Ortaklık ödeme taleplerini görüntüleyin ve yönetin.</p>
    </div>
</div>
<?php if ($flash !== ''): ?>
    <div class="alert alert--success"><?= $text($flash) ?></div>
<?php endif; ?>
<section class="card admin-compact-card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Ödemeler</span>
            <h2 class="card-title">Tüm ödeme talepleri</h2>
        </div>
        <form method="get" action="<?= AdminAuth::url('/affiliate/payouts') ?>" style="display:flex;gap:8px;align-items:center">
            <select name="status" class="select" style="width:auto" onchange="this.form.submit()">
                <option value="">Tümü</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Bekleyen</option>
                <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>İşleniyor</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Tamamlanan</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Reddedilen</option>
            </select>
            <?php if ($status !== ''): ?>
                <a class="btn btn--ghost" href="<?= AdminAuth::url('/affiliate/payouts') ?>">Temizle</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="admin-compact-table-wrap">
        <table class="admin-compact-table">
            <thead>
                <tr><th>ID</th><th>Ortak</th><th>Tutar</th><th>Yöntem</th><th>Durum</th><th>Talep</th><th>İşlem</th></tr>
            </thead>
            <tbody>
                <?php if (empty($payouts)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--t-light)">Henüz ödeme talebi yok.</td></tr>
                <?php else: ?>
                    <?php foreach ($payouts as $p): ?>
                    <tr>
                        <td><span class="data-cell-mono">#<?= $p['id'] ?></span></td>
                        <td>
                            <strong><?= $text($p['affiliate_name']) ?></strong>
                            <br><small style="color:var(--t-light)"><?= $text($p['affiliate_email']) ?></small>
                        </td>
                        <td><span class="data-cell-mono"><?= $money($p['amount']) ?> ₺</span></td>
                        <td><small><?= $text($p['method']) ?></small></td>
                        <td>
                            <?php $sc = match ($p['status']) {
                                'completed' => 'success', 'pending' => 'warning',
                                'processing' => 'primary', 'rejected' => 'danger',
                                'cancelled' => 'danger', default => 'primary'
                            }; ?>
                            <span class="badge <?= $sc ?> dot"><?= $text($p['status']) ?></span>
                        </td>
                        <td><small><?= date('d.m.Y', strtotime($p['requested_at'])) ?></small></td>
                        <td>
                            <button class="btn btn--ghost" style="font-size:11px;padding:4px 8px"
                                    data-admin-modal-inline="#payout-modal-<?= (int) $p['id'] ?>"
                                    data-admin-modal-title="Ödeme #<?= $p['id'] ?>">İşlem</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;font-size:12px;color:var(--t-light)">
        <span><?= number_format($total) ?> kayıt</span>
        <div style="display:flex;gap:4px">
            <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                <a href="<?= AdminAuth::url('/affiliate/payouts?page=' . $p . ($status !== '' ? '&status=' . rawurlencode($status) : '')) ?>"
                   style="padding:4px 8px;border-radius:6px;<?= $p === $page ? 'background:var(--accent);color:#fff;font-weight:700' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
</section>

<!-- Payout Action Modals -->
<div id="modal-payout-actions" style="display:none">
<?php foreach ($payouts as $p): ?>
<div id="payout-modal-<?= $p['id'] ?>" style="display:none">
<div style="padding:16px">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div><strong>Ortak:</strong> <?= $text($p['affiliate_name']) ?></div>
        <div><strong>Kod:</strong> <?= $text($p['referral_code']) ?></div>
        <div><strong>Tutar:</strong> <?= $money($p['amount']) ?> ₺</div>
        <div><strong>Yöntem:</strong> <?= $text($p['method']) ?></div>
        <div><strong>Talep:</strong> <?= date('d.m.Y H:i', strtotime($p['requested_at'])) ?></div>
        <div><strong>Durum:</strong> <?= $text($p['status']) ?></div>
    </div>
    <form method="post" action="<?= AdminAuth::url('/affiliate/payout-update') ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <div class="field">
            <label class="field-label">Yeni Durum</label>
            <select class="select" name="status">
                <option value="pending" <?= $p['status'] === 'pending' ? 'selected' : '' ?>>Bekliyor</option>
                <option value="processing" <?= $p['status'] === 'processing' ? 'selected' : '' ?>>İşleniyor</option>
                <option value="completed" <?= $p['status'] === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                <option value="rejected" <?= $p['status'] === 'rejected' ? 'selected' : '' ?>>Reddedildi</option>
                <option value="cancelled" <?= $p['status'] === 'cancelled' ? 'selected' : '' ?>>İptal Edildi</option>
            </select>
        </div>
        <div class="field">
            <label class="field-label">Admin Notu</label>
            <textarea class="input" name="admin_notes" rows="3"><?= $text($p['admin_notes']) ?></textarea>
        </div>
        <div class="form-actions">
            <button class="btn btn--primary" type="submit">Güncelle</button>
        </div>
    </form>
</div>
</div>
<?php endforeach; ?>
</div>
