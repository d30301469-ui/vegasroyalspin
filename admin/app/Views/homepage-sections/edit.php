<?php

$sections = is_array($sections ?? null) ? $sections : [];
$flash = trim((string) ($flash ?? ''));
$error = trim((string) ($error ?? ''));

$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$previewSrc = static function (mixed $value): string {
    $path = trim((string) $value);
    if ($path === '') {
        return '';
    }
    if (preg_match('/^(https?:)?\/\//i', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
        return $path;
    }

    return '../' . ltrim($path, '/');
};
$sectionPayload = static function (array $section): array {
    return is_array($section['payload'] ?? null) ? $section['payload'] : [];
};
$cardsFor = static function (array $section): array {
    $payload = is_array($section['payload'] ?? null) ? $section['payload'] : [];
    return is_array($payload['items'] ?? null) ? $payload['items'] : [];
};

$renderSectionSettings = static function (string $sectionKey, array $section, array $payload, string $label) use ($h): void {
    ?>
    <input type="hidden" name="sections[<?= $h($sectionKey) ?>][surface]" value="<?= $h($section['surface'] ?? 'all') ?>">
    <div class="homepage-section-settings">
        <label>
            <span>Bölüm adı</span>
            <input class="input" name="sections[<?= $h($sectionKey) ?>][title]" value="<?= $h($section['title'] ?? $label) ?>">
        </label>
        <label>
            <span>Tümü linki</span>
            <input class="input" name="sections[<?= $h($sectionKey) ?>][href]" value="<?= $h($payload['href'] ?? '') ?>">
        </label>
        <label>
            <span>Sıra</span>
            <input class="input" name="sections[<?= $h($sectionKey) ?>][sort_order]" value="<?= $h($section['sort_order'] ?? 0) ?>" inputmode="numeric">
        </label>
        <label>
            <span>Başlangıç</span>
            <input class="input admin-date-input" type="date" name="sections[<?= $h($sectionKey) ?>][start_date]" value="<?= $h(substr((string) ($section['start_date'] ?? ''), 0, 10)) ?>">
        </label>
        <label>
            <span>Bitiş</span>
            <input class="input admin-date-input" type="date" name="sections[<?= $h($sectionKey) ?>][end_date]" value="<?= $h(substr((string) ($section['end_date'] ?? ''), 0, 10)) ?>">
        </label>
        <label>
            <span>Durum</span>
            <select class="input" name="sections[<?= $h($sectionKey) ?>][is_active]">
                <option value="1" <?= !empty($section['is_active']) ? 'selected' : '' ?>>Aktif</option>
                <option value="0" <?= empty($section['is_active']) ? 'selected' : '' ?>>Pasif</option>
            </select>
        </label>
    </div>
    <?php
};

$renderGameRow = static function (string $sectionKey, array $card = []) use ($h, $previewSrc): void {
    $title = (string) ($card['title'] ?? '');
    $image = (string) ($card['image_url'] ?? '');
    $size = (string) ($card['size'] ?? 'normal');
    $active = (bool) ($card['is_active'] ?? true);
    ?>
    <div class="homepage-game-row" data-homepage-card-row>
        <input type="hidden" name="cards[<?= $h($sectionKey) ?>][alt][]" value="<?= $h($card['alt'] ?? '') ?>">
        <input type="hidden" name="cards[<?= $h($sectionKey) ?>][link][]" value="<?= $h($card['link'] ?? '') ?>">
        <input type="hidden" name="cards[<?= $h($sectionKey) ?>][image_fit][]" value="fill">
        <input type="hidden" name="cards[<?= $h($sectionKey) ?>][image_scale][]" value="100">
        <div class="homepage-game-preview">
            <?php if (trim($image) !== ''): ?>
                <img src="<?= $h($previewSrc($image)) ?>" alt="<?= $h($title) ?>" data-homepage-preview>
            <?php else: ?>
                <span data-homepage-preview-empty>Görsel</span>
            <?php endif; ?>
        </div>

        <div class="homepage-game-main">
            <div class="homepage-game-line">
                <label class="homepage-field homepage-field--title">
                    <span>Oyun adı</span>
                    <input class="input" name="cards[<?= $h($sectionKey) ?>][title][]" value="<?= $h($title) ?>" placeholder="Örn: Sweet Bonanza 1000">
                </label>
                <label class="homepage-field homepage-field--image">
                    <span>Görsel path / URL</span>
                    <input class="input" name="cards[<?= $h($sectionKey) ?>][image_url][]" value="<?= $h($image) ?>" placeholder="assets/games-img/..." data-homepage-image-input>
                </label>
                <label class="homepage-field">
                    <span>Oyun ID</span>
                    <input class="input" name="cards[<?= $h($sectionKey) ?>][game_id][]" value="<?= $h($card['game_id'] ?? '') ?>" inputmode="numeric">
                </label>
            </div>
        </div>

        <div class="homepage-game-controls">
            <label>
                <span>Sıra</span>
                <input class="input" name="cards[<?= $h($sectionKey) ?>][sort_order][]" value="<?= $h($card['sort_order'] ?? '') ?>" inputmode="numeric">
            </label>
            <label>
                <span>Boyut</span>
                <select class="input" name="cards[<?= $h($sectionKey) ?>][size][]">
                    <option value="normal" <?= $size === 'normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="featured" <?= $size === 'featured' ? 'selected' : '' ?>>Büyük</option>
                </select>
            </label>
            <label>
                <span>Durum</span>
                <select class="input" name="cards[<?= $h($sectionKey) ?>][is_active][]">
                    <option value="1" <?= $active ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= !$active ? 'selected' : '' ?>>Pasif</option>
                </select>
            </label>
            <button class="btn btn--ghost homepage-remove-btn" type="button" data-remove-homepage-card>Sil</button>
        </div>
    </div>
    <?php
};
?>
<style>
    .homepage-manager {
        display: grid;
        gap: 16px;
    }
    .homepage-section-settings {
        display: grid;
        grid-template-columns: minmax(180px, 1fr) minmax(140px, .8fr) 82px 110px;
        gap: 12px;
        margin-bottom: 14px;
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 14px;
        background: var(--bg-muted);
    }
    .homepage-section-settings label,
    .homepage-game-controls label,
    .homepage-field {
        display: grid;
        gap: 5px;
    }
    .homepage-section-settings span,
    .homepage-game-controls span,
    .homepage-field span {
        color: var(--t-muted);
        font-size: 11px;
        font-weight: 700;
    }
    .homepage-banner-row,
    .homepage-game-row {
        display: grid;
        grid-template-columns: 92px minmax(0, 1fr) minmax(250px, 280px);
        gap: 12px;
        align-items: center;
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 16px;
        background: var(--bg-muted);
        margin-bottom: 10px;
        overflow: hidden;
    }
    .homepage-banner-row {
        grid-template-columns: 120px minmax(0, 1fr) 220px;
    }
    .homepage-game-preview,
    .homepage-banner-preview {
        width: 92px;
        height: 72px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        overflow: hidden;
        background: var(--bg);
        border: 1px dashed var(--border);
        color: var(--t-muted);
        font-size: 11px;
        font-weight: 800;
    }
    .homepage-banner-preview {
        width: 120px;
    }
    .homepage-game-preview img,
    .homepage-banner-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .homepage-game-main,
    .homepage-banner-main {
        display: grid;
        gap: 10px;
        min-width: 0;
    }
    .homepage-game-line,
    .homepage-banner-line {
        display: grid;
        grid-template-columns: minmax(180px, .9fr) minmax(240px, 1.4fr) 120px;
        gap: 10px;
    }
    .homepage-game-controls,
    .homepage-banner-controls {
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr) minmax(0, 1fr);
        gap: 8px;
        align-items: end;
        min-width: 0;
    }
    .homepage-banner-controls {
        grid-template-columns: 78px 96px;
    }
    .homepage-remove-btn {
        grid-column: 1 / -1;
        justify-content: center;
        width: 100%;
    }
    .homepage-game-row > *,
    .homepage-banner-row > *,
    .homepage-game-controls > *,
    .homepage-banner-controls > * {
        min-width: 0;
    }
    .homepage-game-controls .input,
    .homepage-banner-controls .input,
    .homepage-section-settings .input,
    .homepage-field .input {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .homepage-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }
    .homepage-section-count {
        color: var(--t-muted);
        font-size: 12px;
        font-weight: 700;
    }
    @media (max-width: 1100px) {
        .homepage-section-settings,
        .homepage-game-row,
        .homepage-banner-row,
        .homepage-game-line,
        .homepage-banner-line {
            grid-template-columns: 1fr;
        }
        .homepage-game-controls,
        .homepage-banner-controls {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>

<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">İçerik · Ana Sayfa</span>
        <h1 class="hero-title">Ana Sayfa <span class="accent">Vitrin Yönetimi</span></h1>
        <p class="hero-sub">Oyunları görsel önizlemeli liste üzerinden hızlıca düzenleyin. Boş oyun adı veya görsel olan satırlar kaydedilmez.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="homepageSectionsForm">Kaydet</button>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert danger">
        <span class="ico"><svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></span>
        <div class="body"><?= $h($error) ?></div>
    </div>
<?php endif; ?>

<form id="homepageSectionsForm" method="post" action="<?= $h(AdminAuth::url('/homepage-sections')) ?>">
    <input type="hidden" name="_token" value="<?= $h(AdminAuth::csrfToken()) ?>">
    <div class="homepage-manager">
        <?php
        $banner = is_array($sections['withdrawal-banner'] ?? null) ? $sections['withdrawal-banner'] : [];
        $bannerPayload = $sectionPayload($banner);
        $bannerImage = (string) ($bannerPayload['image_url'] ?? '');
        ?>
        <section class="card">
            <div class="homepage-section-head">
                <div>
                    <span class="eyebrow">Banner</span>
                    <h2 class="card-title">Çekim Banner</h2>
                </div>
                <span class="badge solid">withdrawal-banner</span>
            </div>
            <input type="hidden" name="sections[withdrawal-banner][surface]" value="<?= $h($banner['surface'] ?? 'all') ?>">
            <input type="hidden" name="sections[withdrawal-banner][href]" value="<?= $h($bannerPayload['href'] ?? '') ?>">
            <input type="hidden" name="sections[withdrawal-banner][onclick]" value="<?= $h($bannerPayload['onclick'] ?? '') ?>">
            <div class="homepage-banner-row">
                <div class="homepage-banner-preview">
                    <?php if (trim($bannerImage) !== ''): ?>
                        <img src="<?= $h($previewSrc($bannerImage)) ?>" alt="<?= $h($bannerPayload['alt'] ?? '') ?>" data-homepage-preview>
                    <?php else: ?>
                        <span data-homepage-preview-empty>Banner</span>
                    <?php endif; ?>
                </div>
                <div class="homepage-banner-main">
                    <div class="homepage-banner-line">
                        <label class="homepage-field">
                            <span>Başlık</span>
                            <input class="input" name="sections[withdrawal-banner][title]" value="<?= $h($banner['title'] ?? 'Çekim Banner') ?>">
                        </label>
                        <label class="homepage-field">
                            <span>Görsel path / URL</span>
                            <input class="input" name="sections[withdrawal-banner][image_url]" value="<?= $h($bannerImage) ?>" data-homepage-image-input>
                        </label>
                        <label class="homepage-field">
                            <span>Alt metin</span>
                            <input class="input" name="sections[withdrawal-banner][alt]" value="<?= $h($bannerPayload['alt'] ?? '') ?>">
                        </label>
                    </div>
                </div>
                <div class="homepage-banner-controls">
                    <label>
                        <span>Sıra</span>
                        <input class="input" name="sections[withdrawal-banner][sort_order]" value="<?= $h($banner['sort_order'] ?? 10) ?>" inputmode="numeric">
                    </label>
                    <label>
                        <span>Başlangıç</span>
                        <input class="input admin-date-input" type="date" name="sections[withdrawal-banner][start_date]" value="<?= $h(substr((string) ($banner['start_date'] ?? ''), 0, 10)) ?>">
                    </label>
                    <label>
                        <span>Bitiş</span>
                        <input class="input admin-date-input" type="date" name="sections[withdrawal-banner][end_date]" value="<?= $h(substr((string) ($banner['end_date'] ?? ''), 0, 10)) ?>">
                    </label>
                    <label>
                        <span>Durum</span>
                        <select class="input" name="sections[withdrawal-banner][is_active]">
                            <option value="1" <?= !empty($banner['is_active']) ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= empty($banner['is_active']) ? 'selected' : '' ?>>Pasif</option>
                        </select>
                    </label>
                </div>
            </div>
        </section>

        <?php foreach (['casino' => 'Casino', 'live-casino' => 'Canlı Casino'] as $sectionKey => $label): ?>
            <?php
            $section = is_array($sections[$sectionKey] ?? null) ? $sections[$sectionKey] : [];
            $payload = $sectionPayload($section);
            $cards = $cardsFor($section);
            ?>
            <section class="card" data-homepage-section="<?= $h($sectionKey) ?>">
                <div class="homepage-section-head">
                    <div>
                        <span class="eyebrow">Oyun Listesi</span>
                        <h2 class="card-title"><?= $h($label) ?></h2>
                        <div class="homepage-section-count"><?= count($cards) ?> kayıt</div>
                    </div>
                    <button class="btn btn--primary" type="button" data-add-homepage-card="<?= $h($sectionKey) ?>">Yeni Oyun Ekle</button>
                </div>

                <?php $renderSectionSettings($sectionKey, $section, $payload, $label); ?>

                <div data-homepage-card-list>
                    <?php foreach ($cards as $card): ?>
                        <?php $renderGameRow($sectionKey, is_array($card) ? $card : []); ?>
                    <?php endforeach; ?>
                    <?php $renderGameRow($sectionKey, ['sort_order' => (count($cards) + 1) * 10, 'is_active' => true]); ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="form-actions admin-action-spaced">
        <span class="badge dot success">API: /api/v2/content/homepage-sections</span>
        <span class="spacer"></span>
        <button class="btn btn--primary" type="submit">Değişiklikleri Kaydet</button>
    </div>
</form>

<template id="homepageCardTemplate">
    <?php $renderGameRow('__SECTION__', ['is_active' => true]); ?>
</template>
</section>

<script>
function homepagePreviewSrc(value) {
    value = (value || '').trim();
    if (value === '') {
        return '';
    }
    if (/^(https?:)?\/\//i.test(value) || value.charAt(0) === '/' || value.indexOf('data:') === 0 || value.indexOf('blob:') === 0) {
        return value;
    }
    return '../' + value.replace(/^\/+/, '');
}

document.addEventListener('click', function (event) {
    var removeButton = event.target.closest('[data-remove-homepage-card]');
    if (removeButton) {
        var row = removeButton.closest('[data-homepage-card-row]');
        if (row) {
            row.remove();
        }
        return;
    }

    var addButton = event.target.closest('[data-add-homepage-card]');
    if (!addButton) {
        return;
    }
    var sectionKey = addButton.getAttribute('data-add-homepage-card');
    var section = addButton.closest('[data-homepage-section]');
    var list = section ? section.querySelector('[data-homepage-card-list]') : null;
    var template = document.getElementById('homepageCardTemplate');
    if (!sectionKey || !list || !template) {
        return;
    }
    var wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.split('__SECTION__').join(sectionKey);
    while (wrapper.firstElementChild) {
        list.appendChild(wrapper.firstElementChild);
    }
});

document.addEventListener('input', function (event) {
    var input = event.target.closest('[data-homepage-image-input]');
    if (!input) {
        return;
    }
    var row = input.closest('.homepage-game-row, .homepage-banner-row');
    var previewWrap = row ? row.querySelector('.homepage-game-preview, .homepage-banner-preview') : null;
    if (!previewWrap) {
        return;
    }
    var value = input.value.trim();
    if (value === '') {
        previewWrap.innerHTML = '<span data-homepage-preview-empty>Görsel</span>';
        return;
    }
    previewWrap.innerHTML = '';
    var image = document.createElement('img');
    image.src = homepagePreviewSrc(value);
    image.alt = '';
    image.setAttribute('data-homepage-preview', '');
    previewWrap.appendChild(image);
});
</script>
