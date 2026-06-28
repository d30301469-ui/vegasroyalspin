<?php

$promotion = is_array($promotion ?? null) ? $promotion : [];
$mode = (string) ($mode ?? 'create');
$flash = trim((string) ($flash ?? ''));
$isEdit = $mode === 'edit';
$id = (string) ($promotion['id'] ?? '');

$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$val = static fn (string $field, mixed $default = ''): string => htmlspecialchars((string) ($promotion[$field] ?? $default), ENT_QUOTES, 'UTF-8');
?>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Marketing · <?= $isEdit ? 'Düzenle' : 'Ekle' ?></span>
        <h1 class="hero-title"><?= $isEdit ? 'Promosyon <span class="accent">düzenle</span>' : 'Promosyon <span class="accent">ekle</span>' ?></h1>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/promotions')) ?>">Geri dön</a>
    </div>
</div>

<?php if ($flash !== ''): ?>
    <div class="alert alert--danger"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card admin-compact-card" style="max-width:720px">
    <div class="card-head">
        <div class="card-title-wrap">
            <h2 class="card-title"><?= $isEdit ? 'Promosyon bilgilerini düzenle' : 'Yeni promosyon ekle' ?></h2>
        </div>
    </div>
    <form method="post" action="<?= $text(AdminAuth::url($isEdit ? '/promotion/update' : '/promotion/store')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $text($id) ?>"><?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:14px 0">
            <div class="field" style="grid-column:1/-1">
                <label class="field-label" for="promoTitle">Başlık <span style="color:var(--danger)">*</span></label>
                <input id="promoTitle" class="input" type="text" name="title" value="<?= $val('title') ?>" required maxlength="255">
            </div>

            <div class="field" style="grid-column:1/-1">
                <label class="field-label" for="promoDesc">Açıklama</label>
                <textarea id="promoDesc" class="input" name="description" rows="4" style="resize:vertical;height:100px"><?= $val('description') ?></textarea>
            </div>

            <div class="field">
                <label class="field-label" for="promoType">Tip</label>
                <input id="promoType" class="input" type="text" name="type" value="<?= $val('type') ?>" maxlength="60" placeholder="welcome, deposit, freespin...">
            </div>

            <div class="field">
                <label class="field-label" for="promoCategory">Kategori</label>
                <input id="promoCategory" class="input" type="text" name="category" value="<?= $val('category') ?>" maxlength="60" placeholder="casino, sports...">
            </div>

            <div class="field">
                <label class="field-label" for="promoStatus">Durum</label>
                <select id="promoStatus" class="select" name="status">
                    <?php foreach (['active' => 'Aktif', 'inactive' => 'İnaktif', 'draft' => 'Taslak'] as $v => $l): ?>
                        <option value="<?= $text($v) ?>"<?= ($val('status', 'active') === $v ? ' selected' : '') ?>><?= $text($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="field-label" for="promoSortOrder">Sıralama</label>
                <input id="promoSortOrder" class="input" type="number" name="sort_order" value="<?= $val('sort_order', '0') ?>" min="0">
            </div>

            <div class="field">
                <label class="field-label" for="promoBonusAmount">Bonus tutarı (₺)</label>
                <input id="promoBonusAmount" class="input" type="number" name="bonus_amount" value="<?= $val('bonus_amount', '0') ?>" min="0" step="0.01">
            </div>

            <div class="field">
                <label class="field-label" for="promoWagering">Çevrim çarpanı</label>
                <input id="promoWagering" class="input" type="number" name="wagering_multiplier" value="<?= $val('wagering_multiplier', '0') ?>" min="0" step="0.1">
            </div>

            <div class="field" style="grid-column:1/-1">
                <label class="field-label" for="promoImage">Resim URL</label>
                <input id="promoImage" class="input" type="url" name="image_url" value="<?= $val('image_url') ?>" maxlength="500" placeholder="https://...">
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/promotions')) ?>">İptal</a>
            <button class="btn btn--primary" type="submit"><?= $isEdit ? 'Güncelle' : 'Kaydet' ?></button>
        </div>
    </form>
</section>
</section>
