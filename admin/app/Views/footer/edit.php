<?php

$payload = is_array($payload ?? null) ? $payload : [];
$about = is_array($payload['about'] ?? null) ? $payload['about'] : [];
$supportBadge = is_array($payload['support_badge'] ?? null) ? $payload['support_badge'] : [];
$jsonFields = is_array($jsonFields ?? null) ? $jsonFields : [];
$flash = trim((string) ($flash ?? ''));
$error = trim((string) ($error ?? ''));
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$jsonLabels = [
    'social_icons' => 'Sosyal Medya İkonları',
    'menu_columns' => 'Footer Menü Kolonları',
    'payments' => 'Ödeme Logoları',
    'licence_rows' => 'Yönetmelikler & Partnerler',
    'awards' => 'Ödül Görselleri',
    'partner_logos' => 'Sponsor / Lig Logoları (Ana Sayfa Şeridi)',
    'jackpot_config' => 'Jackpot Widget Ayarları (epoch + providers JSON)',
];
$flattenJson = static fn () => [];  // reserved, not used
$jsonFieldRows = static fn (string $field) => [];  // reserved, not used
?>
<style>
    .json-editor-wrap { position:relative; }
    .json-editor-wrap .textarea { font-family: 'Courier New', Courier, monospace; font-size:12px; min-height:180px; white-space:pre; }
    .json-editor-status { font-size:11px; font-weight:700; margin-top:4px; }
    .json-editor-status.ok  { color: var(--success, #22c55e); }
    .json-editor-status.err { color: var(--danger, #ef4444); }
    .json-editor-format-btn { position:absolute; top:8px; right:8px; }
</style>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">İçerik · Footer</span>
        <h1 class="hero-title">Footer <span class="accent">Yönetimi</span></h1>
        <p class="hero-sub">Footer alanlarının tamamı bu ekrandan düzenlenir ve API payload olarak yayınlanır.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $h(AdminAuth::url('/module?key=footer-settings')) ?>">Kayıtları Gör</a>
        <button class="btn btn--primary" type="submit" form="footerSettingsForm">Kaydet</button>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert success admin-alert-spaced">
        <span class="ico"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></span>
        <div class="body"><?= $h($flash) ?></div>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert danger admin-alert-spaced">
        <span class="ico"><svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></span>
        <div class="body"><?= $h($error) ?></div>
    </div>
<?php endif; ?>

<form id="footerSettingsForm" method="post" action="<?= $h(AdminAuth::url('/footer')) ?>">
    <input type="hidden" name="_token" value="<?= $h(AdminAuth::csrfToken()) ?>">

    <div class="grid">
        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">General</span>
                    <h2 class="card-title">Genel Bilgiler</h2>
                </div>
                <label class="switch">
                    <input type="checkbox" name="show_custom_content" value="1" <?= !empty($payload['show_custom_content']) ? 'checked' : '' ?>>
                    <span class="track"></span>
                    Üst özel içerik aktif
                </label>
                <label class="switch">
                    <input type="checkbox" name="support_badge_enabled" value="1" <?= !empty($supportBadge['enabled']) ? 'checked' : '' ?>>
                    <span class="track"></span>
                    7/24 online rozeti aktif
                </label>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="site_name">Site adı</label>
                    <input id="site_name" class="input" name="site_name" value="<?= $h($payload['site_name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="copyright_since">Copyright başlangıç yılı</label>
                    <input id="copyright_since" class="input" type="number" name="copyright_since" value="<?= $h($payload['copyright_since'] ?? 2014) ?>">
                </div>
                <div class="field span-2">
                    <label class="field-label" for="flag_image">Dil bayrağı görsel yolu</label>
                    <input id="flag_image" class="input" name="flag_image" value="<?= $h($payload['flag_image'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="support_badge_label">Online rozet üst metni</label>
                    <input id="support_badge_label" class="input" name="support_badge_label" value="<?= $h($supportBadge['label'] ?? '7/24') ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="support_badge_text">Online rozet alt metni</label>
                    <input id="support_badge_text" class="input" name="support_badge_text" value="<?= $h($supportBadge['text'] ?? 'ONLINE') ?>">
                </div>
                <div class="field span-2">
                    <label class="field-label" for="support_badge_href">Online rozet linki</label>
                    <input id="support_badge_href" class="input" name="support_badge_href" value="<?= $h($supportBadge['href'] ?? 'javascript:void(0)') ?>">
                </div>
            </div>
        </section>

        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">About</span>
                    <h2 class="card-title">Tarihimiz / Geleceğimiz / Ödüllerimiz</h2>
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="history_title">Tarihimiz başlık</label>
                    <input id="history_title" class="input" name="history_title" value="<?= $h($about['history_title'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="future_title">Geleceğimiz başlık</label>
                    <input id="future_title" class="input" name="future_title" value="<?= $h($about['future_title'] ?? '') ?>">
                </div>
                <div class="field span-2">
                    <label class="field-label" for="history_text">Tarihimiz metni</label>
                    <textarea id="history_text" class="textarea" name="history_text" rows="5"><?= $h($about['history_text'] ?? '') ?></textarea>
                </div>
                <div class="field span-2">
                    <label class="field-label" for="future_text">Geleceğimiz metni</label>
                    <textarea id="future_text" class="textarea" name="future_text" rows="5"><?= $h($about['future_text'] ?? '') ?></textarea>
                </div>
                <div class="field span-2">
                    <label class="field-label" for="awards_title">Ödüller başlığı</label>
                    <input id="awards_title" class="input" name="awards_title" value="<?= $h($about['awards_title'] ?? '') ?>">
                </div>
            </div>
        </section>

        <?php foreach ($jsonLabels as $field => $label): ?>
            <section class="col-12 card">
                <div class="card-head">
                    <div class="card-title-wrap">
                        <span class="eyebrow">JSON Verisi</span>
                        <h2 class="card-title"><?= $h($label) ?></h2>
                    </div>
                    <span class="badge solid"><?= $h($field) ?></span>
                </div>
                <div class="field span-2">
                    <div class="json-editor-wrap">
                        <button class="btn btn--ghost json-editor-format-btn" type="button" data-json-format="<?= $h($field) ?>">Formatla</button>
                        <textarea
                            id="json_<?= $h($field) ?>"
                            class="textarea"
                            name="<?= $h($field) ?>"
                            rows="12"
                            spellcheck="false"
                            autocomplete="off"
                            data-json-editor="<?= $h($field) ?>"
                        ><?= $h($jsonFields[$field] ?? '[]') ?></textarea>
                        <div class="json-editor-status ok" data-json-status="<?= $h($field) ?>">✓ Geçerli JSON</div>
                    </div>
                    <div class="field-help">Bu alan API payload içindeki <code><?= $h($field) ?></code> değerini düzenler. Geçerli bir JSON dizisi veya nesnesi olmalıdır.</div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="form-actions admin-action-spaced">
        <span class="badge dot success">API: /api/v2/content/footer</span>
        <span class="spacer"></span>
        <button class="btn btn--primary" type="submit">Footer Ayarlarını Kaydet</button>
    </div>
</form>
<script>
    (function () {
        function validateJson(textarea, statusEl) {
            var val = textarea.value.trim();
            if (val === '') {
                textarea.value = '[]';
                val = '[]';
            }
            try {
                var parsed = JSON.parse(val);
                if (typeof parsed !== 'object' || parsed === null) {
                    throw new Error('Dizi veya nesne gerekli');
                }
                statusEl.textContent = '✓ Geçerli JSON';
                statusEl.className = 'json-editor-status ok';
                return true;
            } catch (e) {
                statusEl.textContent = '✗ Geçersiz JSON: ' + e.message;
                statusEl.className = 'json-editor-status err';
                return false;
            }
        }

        function formatJson(textarea, statusEl) {
            try {
                var parsed = JSON.parse(textarea.value.trim());
                textarea.value = JSON.stringify(parsed, null, 2);
                statusEl.textContent = '✓ Geçerli JSON';
                statusEl.className = 'json-editor-status ok';
            } catch (e) {
                statusEl.textContent = '✗ Geçersiz JSON: ' + e.message;
                statusEl.className = 'json-editor-status err';
            }
        }

        document.querySelectorAll('[data-json-editor]').forEach(function (textarea) {
            var field = textarea.dataset.jsonEditor;
            var statusEl = document.querySelector('[data-json-status="' + field + '"]');
            if (!statusEl) return;
            validateJson(textarea, statusEl);
            textarea.addEventListener('input', function () { validateJson(textarea, statusEl); });
        });

        document.querySelectorAll('[data-json-format]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var field = btn.dataset.jsonFormat;
                var textarea = document.querySelector('[data-json-editor="' + field + '"]');
                var statusEl = document.querySelector('[data-json-status="' + field + '"]');
                if (textarea && statusEl) formatJson(textarea, statusEl);
            });
        });

        var form = document.getElementById('footerSettingsForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                var hasError = false;
                document.querySelectorAll('[data-json-editor]').forEach(function (textarea) {
                    var field = textarea.dataset.jsonEditor;
                    var statusEl = document.querySelector('[data-json-status="' + field + '"]');
                    if (statusEl && !validateJson(textarea, statusEl)) {
                        hasError = true;
                    }
                });
                if (hasError) {
                    e.preventDefault();
                    var firstErr = document.querySelector('.json-editor-status.err');
                    if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    })();
</script>
