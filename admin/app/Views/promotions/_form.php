<?php

$promotion = is_array($promotion ?? null) ? $promotion : [];
$mode = (string) ($mode ?? 'create');
$flash = trim((string) ($flash ?? ''));
$isEdit = $mode === 'edit';
$isModal = (bool) ($isModal ?? false);
$id = (string) ($promotion['id'] ?? '');

$text = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$val = static fn (string $field, mixed $default = ''): string => htmlspecialchars((string) ($promotion[$field] ?? $default), ENT_QUOTES, 'UTF-8');
$categoryOptions = is_array($categoryOptions ?? null) ? $categoryOptions : [];
$libraryImages = is_array($libraryImages ?? null) ? $libraryImages : [];
$currentType = (string) ($promotion['type'] ?? '');
$currentImage = (string) ($promotion['image_url'] ?? '');
?>
<?php if ($flash !== ''): ?>
    <div class="alert alert--danger" style="margin-bottom:12px"><?= $text($flash) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" action="<?= $text(AdminAuth::url($isEdit ? '/promotion/update' : '/promotion/store')) ?>">
    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $text($id) ?>"><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:4px 0">
        <div class="field" style="grid-column:1/-1">
            <label class="field-label" for="promoTitle">Başlık <span style="color:var(--danger)">*</span></label>
            <input id="promoTitle" class="input" type="text" name="title" value="<?= $val('title') ?>" required maxlength="255">
        </div>

        <div class="field" style="grid-column:1/-1">
            <label class="field-label" for="promoDesc">Açıklama</label>
            <textarea id="promoDesc" class="input" name="description" rows="4" style="resize:vertical;height:100px"><?= $val('description') ?></textarea>
        </div>

        <div class="field">
            <label class="field-label" for="promoType">Kategori <span style="color:var(--danger)">*</span></label>
            <select id="promoType" class="select" name="type" required>
                <option value="">Kategori seçin...</option>
                <?php foreach ($categoryOptions as $slug => $label): ?>
                    <option value="<?= $text($slug) ?>"<?= ($currentType === $slug ? ' selected' : '') ?>><?= $text($label) ?></option>
                <?php endforeach; ?>
                <?php if ($currentType !== '' && !array_key_exists($currentType, $categoryOptions)): ?>
                    <option value="<?= $text($currentType) ?>" selected><?= $text($currentType) ?> (eski değer)</option>
                <?php endif; ?>
            </select>
            <small style="display:block;margin-top:6px;color:#667085">Frontend promosyon sayfasindaki kategori filtresini bu değer belirler.</small>
        </div>

        <div class="field">
            <label class="field-label" for="promoCategory">Etiket / alt kategori (opsiyonel)</label>
            <input id="promoCategory" class="input" type="text" name="category" value="<?= $val('category') ?>" maxlength="60" placeholder="opsiyonel serbest etiket">
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
    </div>

    <!-- Bonus Yapılandırması -->
    <div style="grid-column:1/-1;margin-top:10px;padding-top:10px;border-top:2px solid var(--border);">
        <h3 style="margin:0 0 10px 0;font-size:15px;font-weight:700">🎁 Bonus Yapılandırması</h3>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:4px 0">
        <?php
        $currentBonusType = (string) ($promotion['bonus_type'] ?? '');
        $displayBonusType = ($currentBonusType === 'fixed') ? '' : $currentBonusType;
        ?>
        <div class="field">
            <label class="field-label" for="promoBonusType">Bonus tipi <span style="color:var(--danger)">*</span></label>
            <select id="promoBonusType" class="select" name="bonus_type" onchange="updateBonusForm()">
                <option value="">Sabit tutar — promosyona özel TL miktarı</option>
                <option value="percentage">Yüzdesel — toplam onaylı yatırımın % değeri</option>
                <option value="first_deposit_pct">İlk Yatırım Yüzdesi — sadece ilk yatırımın % değeri</option>
            </select>
            <small id="promoBonusTypeHint" style="display:block;margin-top:6px;color:#667085"></small>
        </div>

        <div class="field">
            <label class="field-label" for="promoBonusAmount">
                <span id="promoBonusAmountLabel"></span>
            </label>
            <input id="promoBonusAmount" class="input" type="number" name="bonus_amount" value="<?= $val('bonus_amount', '0') ?>" min="0" step="0.01">
            <small id="promoBonusAmountHint" style="display:block;margin-top:6px;color:#667085"></small>
        </div>

        <div class="field">
            <label class="field-label" for="promoWagering">Çevrim çarpanı</label>
            <input id="promoWagering" class="input" type="number" name="wagering_multiplier" value="<?= $val('wagering_multiplier', '1') ?>" min="0" step="0.1">
            <small style="display:block;margin-top:6px;color:#667085">Bonus × çevrim = çevrilmesi gereken toplam tutar. Örn: 500 TL bonus × 5x = 2.500 TL.</small>
        </div>

        <div class="field">
            <label class="field-label" for="promoBonusRulesToggle" style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" id="promoBonusRulesToggle" onchange="toggleBonusRules()" style="width:auto" <?= (trim($val('bonus_rules')) !== '' ? 'checked' : '') ?>>
                Gelişmiş bonus kuralları (JSON)
            </label>
        </div>

        <div class="field" id="promoBonusRulesField" style="grid-column:1/-1;<?= (trim($val('bonus_rules')) !== '' ? '' : 'display:none') ?>">
            <textarea id="promoBonusRules" class="input" name="bonus_rules" rows="6" style="resize:vertical;font-family:monospace;font-size:12px" placeholder='[
  {"applies_to": "first_deposit", "type": "percentage", "value": 100, "max_amount": 500}
]'><?= $val('bonus_rules') ?></textarea>
            <small style="display:block;margin-top:6px;color:#667085">
                <strong>applies_to:</strong> first_deposit | deposit | any &nbsp;|&nbsp;
                <strong>type:</strong> percentage | fixed &nbsp;|&nbsp;
                <strong>value:</strong> yüzde veya TL &nbsp;|&nbsp;
                <strong>max_amount:</strong> üst limit (opsiyonel)
            </small>
        </div>
    </div>

    <!-- Görsel -->
    <div style="grid-column:1/-1;margin-top:10px;padding-top:10px;border-top:2px solid var(--border);">
        <h3 style="margin:0 0 10px 0;font-size:15px;font-weight:700">🖼️ Görsel</h3>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:4px 0">

        <div class="field" style="grid-column:1/-1">
            <label class="field-label" for="promoImageLibrary">Kütüphaneden görsel seç</label>
            <select id="promoImageLibrary" class="select" onchange="var f=document.getElementById('promoImage'); if(this.value){ f.value = this.value; } document.getElementById('promoImagePreview').src = this.value || f.value;">
                <option value="">-- admin/upload/bonuses kütüphanesinden seçin --</option>
                <?php foreach ($libraryImages as $img): ?>
                    <option value="<?= $text($img['url']) ?>"<?= ($currentImage === $img['url'] ? ' selected' : '') ?>><?= $text($img['filename']) ?></option>
                <?php endforeach; ?>
            </select>
            <small style="display:block;margin-top:6px;color:#667085">Seçilen görsel aşağıdaki URL alanına otomatik yazılır.</small>
        </div>

        <div class="field" style="grid-column:1/-1">
            <label class="field-label" for="promoImage">Resim URL</label>
            <input id="promoImage" class="input" type="text" name="image_url" value="<?= $val('image_url') ?>" maxlength="700" placeholder="https://icons... veya /uploads/..." oninput="document.getElementById('promoImagePreview').src = this.value;">
            <small style="display:block;margin-top:6px;color:#667085">Kaydet ile birlikte image URL veritabaninda otomatik normalize edilir.</small>
        </div>

        <div class="field" style="grid-column:1/-1">
            <label class="field-label" for="promoImageFile">Veya yeni görsel yükle</label>
            <input id="promoImageFile" class="input" type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp,.gif">
            <small style="display:block;margin-top:6px;color:#667085">Yüklenen dosya varsa, URL/kütüphane seçimine öncelikli olarak kullanılır (maks. 5MB).</small>
        </div>

        <div class="field" style="grid-column:1/-1">
            <?php if ($currentImage !== ''): ?>
                <img id="promoImagePreview" src="<?= $text($currentImage) ?>" alt="" style="max-width:160px;max-height:100px;border-radius:8px;border:1px solid var(--border);object-fit:cover">
            <?php else: ?>
                <img id="promoImagePreview" src="" alt="" style="max-width:160px;max-height:100px;border-radius:8px;border:1px solid var(--border);object-fit:cover;display:none">
            <?php endif; ?>
        </div>

        <div class="field" style="grid-column:1/-1">
            <label class="field-label" for="promoLink">Promosyon URL</label>
            <input id="promoLink" class="input" type="text" name="link_url" value="<?= $val('link_url') ?>" maxlength="700" placeholder="/yatirim, /sports veya https://...">
            <small style="display:block;margin-top:6px;color:#667085">Kart icindeki hedef link. Relative path veya tam URL kabul edilir.</small>
        </div>
    </div>

    <div class="form-actions" style="margin-top:4px">
        <?php if ($isModal): ?>
            <button class="btn btn--ghost" type="button" data-admin-modal-close>Vazgec</button>
        <?php else: ?>
            <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/promotions')) ?>">Iptal</a>
        <?php endif; ?>
        <button class="btn btn--primary" type="submit"><?= $isEdit ? 'Guncelle' : 'Kaydet' ?></button>
    </div>
</form>

<script>
// Bonus tipi icin kayitli deger (PHP'den gelir)
var savedBonusType = <?= json_encode($displayBonusType) ?>;

function updateBonusForm() {
    var type = document.getElementById('promoBonusType').value;
    var label = document.getElementById('promoBonusAmountLabel');
    var hint = document.getElementById('promoBonusAmountHint');
    var typeHint = document.getElementById('promoBonusTypeHint');

    if (type === 'first_deposit_pct') {
        label.textContent = 'Bonus yüzdesi (%)';
        hint.textContent = 'Yüzde değeri girin (1-200). Örn: 100 = %100. İlk yatırım × yüzde = bonus.';
        typeHint.textContent = 'Kullanıcının ilk onaylı yatırımı × yüzde. Örn: 500 TL yatırdıysa %100 = 500 TL bonus.';
    } else if (type === 'percentage') {
        label.textContent = 'Bonus yüzdesi (%)';
        hint.textContent = 'Yüzde değeri girin (1-200). Örn: 50 = %50. Toplam yatırım × yüzde = bonus.';
        typeHint.textContent = 'Kullanıcının tüm onaylı yatırımlarının toplamı × yüzde. Örn: 1000 TL toplam yatırım × %50 = 500 TL bonus.';
    } else {
        label.textContent = 'Bonus tutarı (TL)';
        hint.textContent = 'TL cinsinden sabit bonus miktarı. Onaylı yatırım toplamını aşamaz.';
        typeHint.textContent = 'Doğrudan TL cinsinden bonus miktarı. Kullanıcının onaylı yatırım toplamından fazla olamaz.';
    }
}

function toggleBonusRules() {
    var checked = document.getElementById('promoBonusRulesToggle').checked;
    document.getElementById('promoBonusRulesField').style.display = checked ? '' : 'none';
}

// Kayitli degeri dropdown'a uygula ve formu guncelle
(function() {
    var sel = document.getElementById('promoBonusType');
    if (savedBonusType) {
        sel.value = savedBonusType;
    }
    updateBonusForm();
})();
</script>
