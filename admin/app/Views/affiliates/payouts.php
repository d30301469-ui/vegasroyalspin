<?php
$payouts = is_array($payouts ?? null) ? $payouts : [];
$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $v): string => number_format((float) $v, 2, ',', '.');
$flash = (string) ($flash ?? '');
$methodLabel = static fn (string $m): string => match ($m) {
    'crypto' => 'Kripto',
    'bank' => 'Banka',
    'paypal' => 'PayPal',
    default => $m,
};
$decodeDetails = static function (mixed $raw): array {
    $decoded = json_decode((string) ($raw ?? ''), true);
    return is_array($decoded) ? $decoded : [];
};
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Ortaklık Sistemi</span>
        <h1 class="hero-title">Ödeme <span class="accent">yönetimi</span></h1>
        <p class="hero-sub">Ortaklık ödeme taleplerini görüntüleyin ve yönetin. Kripto talepler admin onayıyla MegaPayz’e iletilir.</p>
    </div>
</div>
<?php if ($flash !== ''): ?>
    <div class="alert <?= str_contains(mb_strtolower($flash), 'hata') || str_contains(mb_strtolower($flash), 'başarısız') || str_contains(mb_strtolower($flash), 'geçersiz') ? 'alert--danger' : 'alert--success' ?>"><?= $text($flash) ?></div>
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
                <tr><th>ID</th><th>Ortak</th><th>Tutar</th><th>Yöntem</th><th>Detay</th><th>Durum</th><th>Talep</th><th>İşlem</th></tr>
            </thead>
            <tbody>
                <?php if (empty($payouts)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--t-light)">Henüz ödeme talebi yok.</td></tr>
                <?php else: ?>
                    <?php foreach ($payouts as $p): ?>
                    <?php
                        $details = $decodeDetails($p['method_details'] ?? '');
                        $detailText = match ((string) ($p['method'] ?? '')) {
                            'crypto' => trim((string) ($details['network'] ?? $details['bank_id'] ?? '')) . ' · ' . (string) ($details['wallet_address'] ?? ''),
                            'bank' => (string) ($details['iban'] ?? ''),
                            'paypal' => (string) ($details['paypal_email'] ?? ''),
                            default => '',
                        };
                        $isCryptoPending = ($p['method'] ?? '') === 'crypto' && in_array(($p['status'] ?? ''), ['pending', 'approved'], true) && empty($p['megapayz_trx']);
                    ?>
                    <tr>
                        <td><span class="data-cell-mono">#<?= (int) $p['id'] ?></span></td>
                        <td>
                            <strong><?= $text($p['affiliate_name']) ?></strong>
                            <br><small style="color:var(--t-light)"><?= $text($p['affiliate_email']) ?></small>
                        </td>
                        <td><span class="data-cell-mono"><?= $money($p['amount']) ?> ₺</span></td>
                        <td><small><?= $text($methodLabel((string) ($p['method'] ?? ''))) ?></small></td>
                        <td><small style="max-width:220px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= $text($detailText) ?>"><?= $text($detailText !== '' ? $detailText : '—') ?></small></td>
                        <td>
                            <?php $sc = match ($p['status']) {
                                'completed' => 'success', 'pending' => 'warning',
                                'processing' => 'primary', 'rejected' => 'danger',
                                'cancelled' => 'danger', default => 'primary'
                            }; ?>
                            <span class="badge <?= $sc ?> dot"><?= $text($p['status']) ?></span>
                            <?php if (!empty($p['megapayz_trx'])): ?>
                                <br><small class="data-cell-mono" style="color:var(--t-light)"><?= $text($p['megapayz_trx']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><small><?= date('d.m.Y', strtotime($p['requested_at'])) ?></small></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap">
                            <?php if ($isCryptoPending): ?>
                            <form method="post" action="<?= AdminAuth::url('/affiliate/payout-megapayz') ?>" onsubmit="return confirm('Bu kripto ödemeyi MegaPayz’e göndermek istiyor musunuz?');">
                                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button class="btn btn--primary" style="font-size:11px;padding:4px 8px" type="submit">MegaPayz’e Gönder</button>
                            </form>
                            <?php endif; ?>
                            <button class="btn btn--ghost" style="font-size:11px;padding:4px 8px"
                                    data-admin-modal-inline="#payout-modal-<?= (int) $p['id'] ?>"
                                    data-admin-modal-title="Ödeme #<?= (int) $p['id'] ?>">İşlem</button>
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
<?php $details = $decodeDetails($p['method_details'] ?? ''); ?>
<div id="payout-modal-<?= (int) $p['id'] ?>" style="display:none">
<div style="padding:16px">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div><strong>Ortak:</strong> <?= $text($p['affiliate_name']) ?></div>
        <div><strong>Kod:</strong> <?= $text($p['referral_code']) ?></div>
        <div><strong>Tutar:</strong> <?= $money($p['amount']) ?> ₺</div>
        <div><strong>Yöntem:</strong> <?= $text($methodLabel((string) ($p['method'] ?? ''))) ?></div>
        <div><strong>Talep:</strong> <?= date('d.m.Y H:i', strtotime($p['requested_at'])) ?></div>
        <div><strong>Durum:</strong> <?= $text($p['status']) ?></div>
        <?php if (($p['method'] ?? '') === 'crypto'): ?>
            <div style="grid-column:1/-1"><strong>Ağ:</strong> <?= $text($details['network'] ?? $details['bank_id'] ?? '—') ?></div>
            <div style="grid-column:1/-1"><strong>Cüzdan:</strong> <span class="data-cell-mono"><?= $text($details['wallet_address'] ?? '—') ?></span></div>
        <?php elseif (($p['method'] ?? '') === 'bank'): ?>
            <div style="grid-column:1/-1"><strong>IBAN:</strong> <?= $text($details['iban'] ?? '—') ?></div>
        <?php endif; ?>
        <?php if (!empty($p['megapayz_trx'])): ?>
            <div style="grid-column:1/-1"><strong>MegaPayz TRX:</strong> <span class="data-cell-mono"><?= $text($p['megapayz_trx']) ?></span></div>
        <?php endif; ?>
    </div>
    <?php if (($p['method'] ?? '') === 'crypto' && in_array(($p['status'] ?? ''), ['pending', 'approved'], true) && empty($p['megapayz_trx'])): ?>
    <form method="post" action="<?= AdminAuth::url('/affiliate/payout-megapayz') ?>" style="margin-bottom:14px" onsubmit="return confirm('Bu kripto ödemeyi MegaPayz’e göndermek istiyor musunuz?');">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <button class="btn btn--primary" type="submit" style="width:100%">MegaPayz’e Onayla ve Gönder</button>
    </form>
    <?php endif; ?>
    <form method="post" action="<?= AdminAuth::url('/affiliate/payout-update') ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <div class="field">
            <label class="field-label">Yeni Durum <?= ($p['method'] ?? '') === 'crypto' ? '(kripto tamamlanma: MegaPayz; burada sadece red/iptal)' : '' ?></label>
            <select class="select" name="status">
                <option value="pending" <?= $p['status'] === 'pending' ? 'selected' : '' ?>>Bekliyor</option>
                <?php if (($p['method'] ?? '') === 'crypto'): ?>
                <option value="processing" <?= $p['status'] === 'processing' ? 'selected' : '' ?>>İşleniyor (MegaPayz’e gönder)</option>
                <?php else: ?>
                <option value="processing" <?= $p['status'] === 'processing' ? 'selected' : '' ?>>İşleniyor</option>
                <option value="completed" <?= $p['status'] === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                <?php endif; ?>
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
