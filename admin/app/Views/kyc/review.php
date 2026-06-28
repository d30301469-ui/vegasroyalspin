<?php

$pending = is_array($pending ?? null) ? $pending : [];
$selected = is_array($selected ?? null) ? $selected : null;
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$selectedId = is_array($selected) ? (int) ($selected['id'] ?? 0) : 0;
$selectedStatus = is_array($selected) ? (string) ($selected['status'] ?? '') : '';
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Üyeler · KYC</span>
        <h1 class="hero-title">KYC <span class="accent">İnceleme</span></h1>
        <p class="hero-sub">Bekleyen kimlik doğrulama taleplerini inceleyin, onaylayın veya reddedin.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/module?key=kyc')) ?>">Tüm KYC Kayıtları</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--success"><?= $text($flash) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:minmax(280px,360px) 1fr;gap:16px;align-items:start">
    <section class="card">
        <div class="card-head"><h2 class="card-title">Bekleyen (<?= count($pending) ?>)</h2></div>
        <div class="card-body">
            <?php if ($pending === []): ?>
                <p>Bekleyen talep yok.</p>
            <?php else: ?>
                <?php foreach ($pending as $row): ?>
                    <?php $id = (int) ($row['id'] ?? 0); ?>
                    <a href="<?= $text(AdminAuth::url('/kyc/review?id=' . $id)) ?>"
                       style="display:block;padding:10px 12px;margin-bottom:8px;border:1px solid var(--border);border-radius:10px;text-decoration:none;<?= $id === $selectedId ? 'background:var(--bg-muted)' : '' ?>">
                        <strong>#<?= $id ?> · <?= $text($row['username'] ?? '') ?></strong>
                        <div class="muted"><?= $text($row['document_type'] ?? '') ?> · <?= $text($row['submitted_at'] ?? '') ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <?php if ($selected === null): ?>
            <div class="card-body"><p>İncelemek için soldan bir talep seçin.</p></div>
        <?php else: ?>
            <div class="card-head">
                <h2 class="card-title">Talep #<?= $selectedId ?> · <?= $text($selected['status'] ?? '') ?></h2>
            </div>
            <div class="card-body">
                <dl style="display:grid;grid-template-columns:140px 1fr;gap:8px 12px;margin-bottom:16px;font-size:13px">
                    <dt style="color:var(--t-light);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding-top:2px">Üye</dt><dd style="color:var(--t-base);margin:0"><?= $text($selected['username'] ?? '') ?> (#<?= (int) ($selected['user_id'] ?? 0) ?>)</dd>
                    <dt style="color:var(--t-light);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding-top:2px">E-posta</dt><dd style="color:var(--t-base);margin:0;font-family:'JetBrains Mono',monospace;font-size:12px"><?= $text($selected['email'] ?? '') ?></dd>
                    <dt style="color:var(--t-light);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding-top:2px">Belge türü</dt><dd style="color:var(--t-base);margin:0"><?= $text($selected['document_type'] ?? '') ?></dd>
                    <dt style="color:var(--t-light);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding-top:2px">Gönderim</dt><dd style="color:var(--t-muted);margin:0"><?= $text($selected['submitted_at'] ?? '') ?></dd>
                    <dt style="color:var(--t-light);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding-top:2px">İnceleyen</dt><dd style="color:var(--t-base);margin:0"><?= $text($selected['reviewed_by'] ?? '—') ?></dd>
                    <dt style="color:var(--t-light);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding-top:2px">Not</dt><dd style="color:var(--t-muted);margin:0"><?= $text($selected['note'] ?? '—') ?></dd>
                </dl>

                <?php $docPath = trim((string) ($selected['document_path'] ?? '')); ?>
                <?php if ($docPath !== ''): ?>
                    <p><a class="btn btn--ghost btn--sm" href="<?= $text($docPath) ?>" target="_blank" rel="noopener">Belgeyi aç</a></p>
                <?php endif; ?>

                <?php if ($selectedStatus === 'pending'): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:16px">
                        <form method="post" action="<?= $text(AdminAuth::url('/kyc/approve')) ?>">
                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $selectedId ?>">
                            <button class="btn btn--primary" type="submit">Onayla</button>
                        </form>
                        <form method="post" action="<?= $text(AdminAuth::url('/kyc/reject')) ?>" style="flex:1;min-width:260px">
                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $selectedId ?>">
                            <div class="field" style="margin-bottom:8px">
                                <label class="field-label" for="note">Red nedeni (opsiyonel)</label>
                                <input id="note" class="input" name="note" type="text" maxlength="500" placeholder="Eksik belge, okunamıyor...">
                            </div>
                            <button class="btn btn--ghost" type="submit">Reddet</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
