<?php

$payload = is_array($payload ?? null) ? $payload : [];
$about = is_array($payload['about'] ?? null) ? $payload['about'] : [];
$supportBadge = is_array($payload['support_badge'] ?? null) ? $payload['support_badge'] : [];
$jsonFields = is_array($jsonFields ?? null) ? $jsonFields : [];
$flash = trim((string) ($flash ?? ''));
$error = trim((string) ($error ?? ''));
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

?>
<style>
    .json-editor-wrap { position:relative; }
    .json-editor-wrap .textarea { font-family: 'Courier New', Courier, monospace; font-size:12px; min-height:180px; white-space:pre; }
    .json-editor-status { font-size:11px; font-weight:700; margin-top:4px; }
    .json-editor-status.ok  { color: var(--success, #22c55e); }
    .json-editor-status.err { color: var(--danger, #ef4444); }
    .json-editor-format-btn { position:absolute; top:8px; right:8px; }
    .visually-hidden { position:absolute !important; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    .repeater-list { display:flex; flex-direction:column; gap:12px; }
    .repeater-item { border:1px solid var(--border, #2b2d32); border-radius:10px; padding:12px 14px; background:var(--bg-muted, rgba(0,0,0,.15)); }
    .repeater-item-head { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
    .repeater-item-title { font-size:12px; font-weight:700; color:var(--t-muted, #9aa1ac); flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .repeater-item-actions { display:flex; gap:6px; flex-shrink:0; }
    .repeater-item-actions button { border:1px solid var(--border, #2b2d32); background:transparent; color:inherit; border-radius:6px; width:26px; height:26px; cursor:pointer; font-size:12px; line-height:1; }
    .repeater-item-actions button:hover { background:var(--bg-hover, rgba(255,255,255,.06)); }
    .repeater-item-actions .repeater-remove-btn { color:var(--danger, #ef4444); border-color:var(--danger, #ef4444); }
    .repeater-fields { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
    .repeater-fields .field.span-2 { grid-column:span 2; }
    .repeater-empty { color:var(--t-muted, #9aa1ac); font-size:12px; padding:14px; text-align:center; border:1px dashed var(--border, #2b2d32); border-radius:10px; }
    .repeater-preview { width:36px; height:36px; object-fit:contain; border-radius:6px; background:rgba(255,255,255,.06); margin-right:8px; flex-shrink:0; }
    .repeater-sub-wrap { margin-top:10px; padding-top:10px; border-top:1px dashed var(--border, #2b2d32); }
    .repeater-sub-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
    .repeater-sub-head span { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--t-muted, #9aa1ac); }
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

        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Sosyal Medya</span>
                    <h2 class="card-title">Sosyal Medya İkonları</h2>
                </div>
                <button class="btn btn--ghost" type="button" data-repeater-add="social_icons">+ İkon Ekle</button>
            </div>
            <div class="repeater-list" data-repeater-list="social_icons"></div>
            <textarea class="visually-hidden" name="social_icons" data-json-editor="social_icons" data-repeater-store="social_icons" aria-hidden="true" tabindex="-1"><?= $h($jsonFields['social_icons'] ?? '[]') ?></textarea>
            <div class="field-help">Footer üstünde görünen sosyal medya ikonları. "Ağ" değeri ikon tipini belirler (twitter, telegram, instagram, youtube, whatsapp, twitch...).</div>
        </section>

        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Menü</span>
                    <h2 class="card-title">Footer Menü Kolonları</h2>
                </div>
                <button class="btn btn--ghost" type="button" data-repeater-add="menu_columns">+ Kolon Ekle</button>
            </div>
            <div class="repeater-list" data-repeater-list="menu_columns"></div>
            <textarea class="visually-hidden" name="menu_columns" data-json-editor="menu_columns" data-repeater-store="menu_columns" aria-hidden="true" tabindex="-1"><?= $h($jsonFields['menu_columns'] ?? '[]') ?></textarea>
            <div class="field-help">Footer'daki her kolonun başlığı ve içindeki linkleri buradan yönetilir. Bağlantı boş bırakılır veya "javascript:void(0)" yazılırsa, link başlığından otomatik bir footer sayfası bağlantısı üretilir.</div>
        </section>

        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Ödemeler</span>
                    <h2 class="card-title">Ödeme Logoları</h2>
                </div>
                <button class="btn btn--ghost" type="button" data-repeater-add="payments">+ Logo Ekle</button>
            </div>
            <div class="repeater-list" data-repeater-list="payments"></div>
            <textarea class="visually-hidden" name="payments" data-json-editor="payments" data-repeater-store="payments" aria-hidden="true" tabindex="-1"><?= $h($jsonFields['payments'] ?? '[]') ?></textarea>
            <div class="field-help">Footer'daki "ÖDEMELER" şeridinde kayar şekilde gösterilen ödeme yöntemi logoları.</div>
        </section>

        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Yönetmelikler</span>
                    <h2 class="card-title">Yönetmelikler &amp; Ortaklar</h2>
                </div>
                <button class="btn btn--ghost" type="button" data-repeater-add="licence_rows">+ Satır Ekle</button>
            </div>
            <div class="repeater-list" data-repeater-list="licence_rows"></div>
            <textarea class="visually-hidden" name="licence_rows" data-json-editor="licence_rows" data-repeater-store="licence_rows" aria-hidden="true" tabindex="-1"><?= $h($jsonFields['licence_rows'] ?? '[]') ?></textarea>
            <div class="field-help">Her satır; metin (ör. lisans yazısı), görsel (ör. ortak logosu) veya iframe (ör. lisans doğrulama widget'ı) türünde öğelerden oluşabilir.</div>
        </section>

        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Ödüller</span>
                    <h2 class="card-title">Ödül Görselleri</h2>
                </div>
                <button class="btn btn--ghost" type="button" data-repeater-add="awards">+ Görsel Ekle</button>
            </div>
            <div class="repeater-list" data-repeater-list="awards"></div>
            <textarea class="visually-hidden" name="awards" data-json-editor="awards" data-repeater-store="awards" aria-hidden="true" tabindex="-1"><?= $h($jsonFields['awards'] ?? '[]') ?></textarea>
            <div class="field-help">"Üst özel içerik" bölümündeki "ÖDÜLLERİMİZ" panelinde gösterilen görseller.</div>
        </section>

        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Ana Sayfa Şeridi</span>
                    <h2 class="card-title">Sponsor / Lig Logoları</h2>
                </div>
                <button class="btn btn--ghost" type="button" data-repeater-add="partner_logos">+ Logo Ekle</button>
            </div>
            <div class="repeater-list" data-repeater-list="partner_logos"></div>
            <textarea class="visually-hidden" name="partner_logos" data-json-editor="partner_logos" data-repeater-store="partner_logos" aria-hidden="true" tabindex="-1"><?= $h($jsonFields['partner_logos'] ?? '[]') ?></textarea>
            <div class="field-help">Footer'a değil, ana sayfadaki "ligler" şeridine ait sponsor/lig logoları. Boş bırakılırsa <code>assets/images/ligler</code> klasöründeki görseller otomatik kullanılır.</div>
        </section>

        <section class="col-12 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Gelişmiş</span>
                    <h2 class="card-title">Jackpot Widget Ayarları</h2>
                </div>
                <span class="badge solid">jackpot_config</span>
            </div>
            <div class="field span-2">
                <div class="json-editor-wrap">
                    <button class="btn btn--ghost json-editor-format-btn" type="button" data-json-format="jackpot_config">Formatla</button>
                    <textarea
                        id="json_jackpot_config"
                        class="textarea"
                        name="jackpot_config"
                        rows="12"
                        spellcheck="false"
                        autocomplete="off"
                        data-json-editor="jackpot_config"
                    ><?= $h($jsonFields['jackpot_config'] ?? '[]') ?></textarea>
                    <div class="json-editor-status ok" data-json-status="jackpot_config">✓ Geçerli JSON</div>
                </div>
                <div class="field-help">Ana sayfadaki jackpot widget'ının epoch ve sağlayıcı/kademe (tier) artış ayarları. Karmaşık iç içe yapısından dolayı bu alan JSON olarak düzenlenir.</div>
            </div>
        </section>
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

        // ---------------------------------------------------------------
        // Repeater builders (görsel form -> gizli JSON textarea senkronu)
        // ---------------------------------------------------------------
        function escapeAttr(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function readStore(field) {
            var textarea = document.querySelector('[data-repeater-store="' + field + '"]');
            if (!textarea) return null;
            var raw = textarea.value.trim();
            var data;
            try {
                data = raw === '' ? [] : JSON.parse(raw);
            } catch (e) {
                data = [];
            }
            if (!Array.isArray(data)) data = [];
            return { textarea: textarea, data: data };
        }

        function writeStore(textarea, data) {
            textarea.value = JSON.stringify(data);
        }

        function selectOptions(current, options) {
            return options.map(function (opt) {
                var selected = String(current) === opt ? ' selected' : '';
                return '<option value="' + escapeAttr(opt) + '"' + selected + '>' + escapeAttr(opt) + '</option>';
            }).join('');
        }

        // ---- Basit (tek seviyeli) tekrar eden alanlar -------------------
        var FLAT_REPEATERS = {
            social_icons: {
                empty: function () { return { network: 'twitter', url: '' }; },
                addLabel: 'İkon',
                title: function (item) { return item.network || 'İkon'; },
                render: function (item, idx) {
                    return '<div class="repeater-fields">'
                        + '<div class="field"><label class="field-label">Ağ (network)</label>'
                        + '<select class="select" data-field="network" data-index="' + idx + '" list="social-network-options">'
                        + selectOptions(item.network || '', ['twitter', 'telegram', 'instagram', 'youtube', 'whatsapp', 'twitch'])
                        + '</select></div>'
                        + '<div class="field"><label class="field-label">Bağlantı</label>'
                        + '<input class="input" data-field="url" data-index="' + idx + '" value="' + escapeAttr(item.url) + '" placeholder="https://..."></div>'
                        + '</div>';
                }
            },
            payments: {
                empty: function () { return { image: '', name: '' }; },
                addLabel: 'Ödeme yöntemi',
                title: function (item) { return item.name || 'Ödeme yöntemi'; },
                render: function (item, idx) {
                    var preview = item.image ? '<img class="repeater-preview" src="' + escapeAttr(item.image) + '" alt="" onerror="this.style.visibility=\'hidden\'">' : '';
                    return '<div class="repeater-fields">'
                        + '<div class="field span-2"><label class="field-label">Görsel yolu</label>'
                        + '<div style="display:flex;align-items:center;">' + preview
                        + '<input class="input" data-field="image" data-index="' + idx + '" value="' + escapeAttr(item.image) + '" placeholder="/assets/images/footer/payments/logo.png"></div></div>'
                        + '<div class="field"><label class="field-label">İsim</label>'
                        + '<input class="input" data-field="name" data-index="' + idx + '" value="' + escapeAttr(item.name) + '"></div>'
                        + '</div>';
                }
            },
            awards: {
                empty: function () { return { src: '', alt: '' }; },
                addLabel: 'Ödül görseli',
                title: function (item) { return item.alt || 'Ödül görseli'; },
                render: function (item, idx) {
                    var preview = item.src ? '<img class="repeater-preview" src="' + escapeAttr(item.src) + '" alt="" onerror="this.style.visibility=\'hidden\'">' : '';
                    return '<div class="repeater-fields">'
                        + '<div class="field span-2"><label class="field-label">Görsel yolu</label>'
                        + '<div style="display:flex;align-items:center;">' + preview
                        + '<input class="input" data-field="src" data-index="' + idx + '" value="' + escapeAttr(item.src) + '" placeholder="/assets/images/footer/awards/odul.png"></div></div>'
                        + '<div class="field"><label class="field-label">Alt metin</label>'
                        + '<input class="input" data-field="alt" data-index="' + idx + '" value="' + escapeAttr(item.alt) + '"></div>'
                        + '</div>';
                }
            },
            partner_logos: {
                empty: function () { return { src: '', alt: '', href: '' }; },
                addLabel: 'Logo',
                title: function (item) { return item.alt || 'Logo'; },
                render: function (item, idx) {
                    var preview = item.src ? '<img class="repeater-preview" src="' + escapeAttr(item.src) + '" alt="" onerror="this.style.visibility=\'hidden\'">' : '';
                    return '<div class="repeater-fields">'
                        + '<div class="field span-2"><label class="field-label">Görsel yolu</label>'
                        + '<div style="display:flex;align-items:center;">' + preview
                        + '<input class="input" data-field="src" data-index="' + idx + '" value="' + escapeAttr(item.src) + '" placeholder="/assets/images/ligler/logo.png"></div></div>'
                        + '<div class="field"><label class="field-label">Alt metin</label>'
                        + '<input class="input" data-field="alt" data-index="' + idx + '" value="' + escapeAttr(item.alt) + '"></div>'
                        + '<div class="field"><label class="field-label">Bağlantı (opsiyonel)</label>'
                        + '<input class="input" data-field="href" data-index="' + idx + '" value="' + escapeAttr(item.href) + '" placeholder="https://..."></div>'
                        + '</div>';
                }
            }
        };

        function initFlatRepeater(field) {
            var config = FLAT_REPEATERS[field];
            var store = readStore(field);
            var list = document.querySelector('[data-repeater-list="' + field + '"]');
            var addBtn = document.querySelector('[data-repeater-add="' + field + '"]');
            if (!config || !store || !list) return;
            var data = store.data;

            function sync() { writeStore(store.textarea, data); }

            function render() {
                if (data.length === 0) {
                    list.innerHTML = '<div class="repeater-empty">Henüz kayıt eklenmedi.</div>';
                    return;
                }
                list.innerHTML = data.map(function (item, idx) {
                    return '<div class="repeater-item" data-item-index="' + idx + '">'
                        + '<div class="repeater-item-head">'
                        + '<span class="repeater-item-title">#' + (idx + 1) + ' — ' + escapeAttr(config.title(item)) + '</span>'
                        + '<div class="repeater-item-actions">'
                        + (idx > 0 ? '<button type="button" data-action="move-up" data-index="' + idx + '" title="Yukarı taşı">&uarr;</button>' : '')
                        + (idx < data.length - 1 ? '<button type="button" data-action="move-down" data-index="' + idx + '" title="Aşağı taşı">&darr;</button>' : '')
                        + '<button type="button" class="repeater-remove-btn" data-action="remove" data-index="' + idx + '" title="Sil">&times;</button>'
                        + '</div></div>'
                        + config.render(item, idx)
                        + '</div>';
                }).join('');
            }

            render();
            sync();

            list.addEventListener('input', function (e) {
                var target = e.target;
                var key = target.getAttribute('data-field');
                if (!key) return;
                var idx = parseInt(target.getAttribute('data-index'), 10);
                if (isNaN(idx) || !data[idx]) return;
                data[idx][key] = target.value;
                sync();
            });
            list.addEventListener('change', function (e) {
                var target = e.target;
                var key = target.getAttribute('data-field');
                if (!key || target.tagName !== 'SELECT') return;
                var idx = parseInt(target.getAttribute('data-index'), 10);
                if (isNaN(idx) || !data[idx]) return;
                data[idx][key] = target.value;
                sync();
                render();
            });
            list.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-action]');
                if (!btn) return;
                var idx = parseInt(btn.getAttribute('data-index'), 10);
                var action = btn.getAttribute('data-action');
                if (action === 'remove') {
                    data.splice(idx, 1);
                } else if (action === 'move-up' && idx > 0) {
                    var tmp = data[idx - 1]; data[idx - 1] = data[idx]; data[idx] = tmp;
                } else if (action === 'move-down' && idx < data.length - 1) {
                    var tmp2 = data[idx + 1]; data[idx + 1] = data[idx]; data[idx] = tmp2;
                }
                render();
                sync();
            });
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    data.push(config.empty());
                    render();
                    sync();
                });
            }
        }

        ['social_icons', 'payments', 'awards', 'partner_logos'].forEach(initFlatRepeater);

        // ---- Menü kolonları (iç içe: kolon -> linkler) ------------------
        (function initMenuColumns() {
            var field = 'menu_columns';
            var store = readStore(field);
            var list = document.querySelector('[data-repeater-list="' + field + '"]');
            var addBtn = document.querySelector('[data-repeater-add="' + field + '"]');
            if (!store || !list) return;
            var data = store.data;

            function sync() { writeStore(store.textarea, data); }

            function renderLinks(col, colIdx) {
                var links = Array.isArray(col.links) ? col.links : (col.links = []);
                if (links.length === 0) {
                    return '<div class="repeater-empty">Bu kolonda henüz link yok.</div>';
                }
                return links.map(function (link, linkIdx) {
                    return '<div class="repeater-item" data-col="' + colIdx + '" data-item-index="' + linkIdx + '">'
                        + '<div class="repeater-item-head">'
                        + '<span class="repeater-item-title">#' + (linkIdx + 1) + ' — ' + escapeAttr(link.title || 'Link') + '</span>'
                        + '<div class="repeater-item-actions">'
                        + (linkIdx > 0 ? '<button type="button" data-action="move-link-up" data-col="' + colIdx + '" data-index="' + linkIdx + '" title="Yukarı">&uarr;</button>' : '')
                        + (linkIdx < links.length - 1 ? '<button type="button" data-action="move-link-down" data-col="' + colIdx + '" data-index="' + linkIdx + '" title="Aşağı">&darr;</button>' : '')
                        + '<button type="button" class="repeater-remove-btn" data-action="remove-link" data-col="' + colIdx + '" data-index="' + linkIdx + '" title="Sil">&times;</button>'
                        + '</div></div>'
                        + '<div class="repeater-fields">'
                        + '<div class="field"><label class="field-label">Başlık</label>'
                        + '<input class="input" data-field="title" data-col="' + colIdx + '" data-index="' + linkIdx + '" value="' + escapeAttr(link.title) + '"></div>'
                        + '<div class="field"><label class="field-label">Bağlantı</label>'
                        + '<input class="input" data-field="href" data-col="' + colIdx + '" data-index="' + linkIdx + '" value="' + escapeAttr(link.href) + '" placeholder="javascript:void(0) veya /sayfa"></div>'
                        + '<div class="field"><label class="field-label">Hedef</label>'
                        + '<select class="select" data-field="target" data-col="' + colIdx + '" data-index="' + linkIdx + '">'
                        + selectOptions(link.target || '_self', ['_self', '_blank'])
                        + '</select></div>'
                        + '<div class="field"><label class="field-label">İkon (opsiyonel)</label>'
                        + '<input class="input" data-field="icon" data-col="' + colIdx + '" data-index="' + linkIdx + '" value="' + escapeAttr(link.icon) + '"></div>'
                        + '</div></div>';
                }).join('');
            }

            function render() {
                if (data.length === 0) {
                    list.innerHTML = '<div class="repeater-empty">Henüz kolon eklenmedi.</div>';
                    return;
                }
                list.innerHTML = data.map(function (col, colIdx) {
                    return '<div class="repeater-item" data-item-index="' + colIdx + '">'
                        + '<div class="repeater-item-head">'
                        + '<span class="repeater-item-title">#' + (colIdx + 1) + ' — ' + escapeAttr(col.title || 'Kolon') + '</span>'
                        + '<div class="repeater-item-actions">'
                        + (colIdx > 0 ? '<button type="button" data-action="move-up" data-index="' + colIdx + '" title="Yukarı">&uarr;</button>' : '')
                        + (colIdx < data.length - 1 ? '<button type="button" data-action="move-down" data-index="' + colIdx + '" title="Aşağı">&darr;</button>' : '')
                        + '<button type="button" class="repeater-remove-btn" data-action="remove" data-index="' + colIdx + '" title="Kolonu sil">&times;</button>'
                        + '</div></div>'
                        + '<div class="repeater-fields">'
                        + '<div class="field"><label class="field-label">Kolon başlığı</label>'
                        + '<input class="input" data-field="title" data-index="' + colIdx + '" value="' + escapeAttr(col.title) + '"></div>'
                        + '<div class="field"><label class="field-label">Kolon ikonu (opsiyonel)</label>'
                        + '<input class="input" data-field="icon" data-index="' + colIdx + '" value="' + escapeAttr(col.icon) + '"></div>'
                        + '</div>'
                        + '<div class="repeater-sub-wrap">'
                        + '<div class="repeater-sub-head"><span>Linkler</span>'
                        + '<button type="button" class="btn btn--ghost" data-action="add-link" data-col="' + colIdx + '">+ Link Ekle</button></div>'
                        + '<div class="repeater-list">' + renderLinks(col, colIdx) + '</div>'
                        + '</div>'
                        + '</div>';
                }).join('');
            }

            render();
            sync();

            list.addEventListener('input', function (e) {
                var target = e.target;
                var key = target.getAttribute('data-field');
                if (!key) return;
                var colIdx = target.getAttribute('data-col');
                var idx = parseInt(target.getAttribute('data-index'), 10);
                if (colIdx === null) {
                    if (!data[idx]) return;
                    data[idx][key] = target.value;
                } else {
                    var col = data[parseInt(colIdx, 10)];
                    if (!col || !col.links || !col.links[idx]) return;
                    col.links[idx][key] = target.value;
                }
                sync();
            });
            list.addEventListener('change', function (e) {
                var target = e.target;
                if (target.tagName !== 'SELECT') return;
                var key = target.getAttribute('data-field');
                var colIdx = target.getAttribute('data-col');
                var idx = parseInt(target.getAttribute('data-index'), 10);
                if (colIdx !== null && data[parseInt(colIdx, 10)] && data[parseInt(colIdx, 10)].links[idx]) {
                    data[parseInt(colIdx, 10)].links[idx][key] = target.value;
                }
                sync();
            });
            list.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-action]');
                if (!btn) return;
                var action = btn.getAttribute('data-action');
                var colIdxAttr = btn.getAttribute('data-col');
                var idx = parseInt(btn.getAttribute('data-index'), 10);

                if (action === 'remove') {
                    data.splice(idx, 1);
                } else if (action === 'move-up' && idx > 0) {
                    var t = data[idx - 1]; data[idx - 1] = data[idx]; data[idx] = t;
                } else if (action === 'move-down' && idx < data.length - 1) {
                    var t2 = data[idx + 1]; data[idx + 1] = data[idx]; data[idx] = t2;
                } else if (action === 'add-link') {
                    var col = data[parseInt(colIdxAttr, 10)];
                    if (col) {
                        if (!Array.isArray(col.links)) col.links = [];
                        col.links.push({ title: '', href: 'javascript:void(0)', target: '_self', icon: '' });
                    }
                } else if (colIdxAttr !== null) {
                    var colIdx2 = parseInt(colIdxAttr, 10);
                    var col2 = data[colIdx2];
                    if (col2 && Array.isArray(col2.links)) {
                        if (action === 'remove-link') {
                            col2.links.splice(idx, 1);
                        } else if (action === 'move-link-up' && idx > 0) {
                            var lt = col2.links[idx - 1]; col2.links[idx - 1] = col2.links[idx]; col2.links[idx] = lt;
                        } else if (action === 'move-link-down' && idx < col2.links.length - 1) {
                            var lt2 = col2.links[idx + 1]; col2.links[idx + 1] = col2.links[idx]; col2.links[idx] = lt2;
                        }
                    }
                }
                render();
                sync();
            });

            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    data.push({ title: '', icon: '', links: [] });
                    render();
                    sync();
                });
            }
        })();

        // ---- Yönetmelikler & Ortaklar (iç içe: satır -> öğeler) ---------
        (function initLicenceRows() {
            var field = 'licence_rows';
            var store = readStore(field);
            var list = document.querySelector('[data-repeater-list="' + field + '"]');
            var addBtn = document.querySelector('[data-repeater-add="' + field + '"]');
            if (!store || !list) return;
            var data = store.data;

            function sync() { writeStore(store.textarea, data); }

            function renderItemFields(item, rowIdx, itemIdx) {
                var type = item.type || 'text';
                if (type === 'text') {
                    return '<div class="field span-2"><label class="field-label">HTML içerik</label>'
                        + '<textarea class="textarea" rows="3" data-field="html" data-row="' + rowIdx + '" data-index="' + itemIdx + '">' + escapeAttr(item.html) + '</textarea></div>';
                }
                if (type === 'image') {
                    return '<div class="field"><label class="field-label">Görsel yolu</label>'
                        + '<input class="input" data-field="src" data-row="' + rowIdx + '" data-index="' + itemIdx + '" value="' + escapeAttr(item.src) + '"></div>'
                        + '<div class="field"><label class="field-label">Bağlantı</label>'
                        + '<input class="input" data-field="href" data-row="' + rowIdx + '" data-index="' + itemIdx + '" value="' + escapeAttr(item.href) + '" placeholder="https://..."></div>';
                }
                if (type === 'iframe') {
                    return '<div class="field span-2"><label class="field-label">Iframe src</label>'
                        + '<input class="input" data-field="src" data-row="' + rowIdx + '" data-index="' + itemIdx + '" value="' + escapeAttr(item.src) + '"></div>';
                }
                return '';
            }

            function renderItems(row, rowIdx) {
                var items = Array.isArray(row) ? row : [];
                if (items.length === 0) {
                    return '<div class="repeater-empty">Bu satırda henüz öğe yok.</div>';
                }
                return items.map(function (item, itemIdx) {
                    return '<div class="repeater-item" data-row="' + rowIdx + '" data-item-index="' + itemIdx + '">'
                        + '<div class="repeater-item-head">'
                        + '<span class="repeater-item-title">#' + (itemIdx + 1) + ' — ' + escapeAttr(item.type || 'text') + '</span>'
                        + '<div class="repeater-item-actions">'
                        + '<button type="button" class="repeater-remove-btn" data-action="remove-item" data-row="' + rowIdx + '" data-index="' + itemIdx + '" title="Sil">&times;</button>'
                        + '</div></div>'
                        + '<div class="repeater-fields">'
                        + '<div class="field"><label class="field-label">Tür</label>'
                        + '<select class="select" data-field="type" data-row="' + rowIdx + '" data-index="' + itemIdx + '" data-rerender="1">'
                        + selectOptions(item.type || 'text', ['text', 'image', 'iframe'])
                        + '</select></div>'
                        + renderItemFields(item, rowIdx, itemIdx)
                        + '</div></div>';
                }).join('');
            }

            function render() {
                if (data.length === 0) {
                    list.innerHTML = '<div class="repeater-empty">Henüz satır eklenmedi.</div>';
                    return;
                }
                list.innerHTML = data.map(function (row, rowIdx) {
                    return '<div class="repeater-item" data-item-index="' + rowIdx + '">'
                        + '<div class="repeater-item-head">'
                        + '<span class="repeater-item-title">Satır #' + (rowIdx + 1) + '</span>'
                        + '<div class="repeater-item-actions">'
                        + (rowIdx > 0 ? '<button type="button" data-action="move-up" data-index="' + rowIdx + '" title="Yukarı">&uarr;</button>' : '')
                        + (rowIdx < data.length - 1 ? '<button type="button" data-action="move-down" data-index="' + rowIdx + '" title="Aşağı">&darr;</button>' : '')
                        + '<button type="button" class="repeater-remove-btn" data-action="remove-row" data-index="' + rowIdx + '" title="Satırı sil">&times;</button>'
                        + '</div></div>'
                        + '<div class="repeater-sub-wrap">'
                        + '<div class="repeater-sub-head"><span>Öğeler</span>'
                        + '<button type="button" class="btn btn--ghost" data-action="add-item" data-row="' + rowIdx + '">+ Öğe Ekle</button></div>'
                        + '<div class="repeater-list">' + renderItems(row, rowIdx) + '</div>'
                        + '</div>'
                        + '</div>';
                }).join('');
            }

            render();
            sync();

            list.addEventListener('input', function (e) {
                var target = e.target;
                var key = target.getAttribute('data-field');
                if (!key) return;
                var rowIdx = parseInt(target.getAttribute('data-row'), 10);
                var idx = parseInt(target.getAttribute('data-index'), 10);
                if (!data[rowIdx] || !data[rowIdx][idx]) return;
                data[rowIdx][idx][key] = target.value;
                sync();
            });
            list.addEventListener('change', function (e) {
                var target = e.target;
                var key = target.getAttribute('data-field');
                if (!key || target.tagName !== 'SELECT') return;
                var rowIdx = parseInt(target.getAttribute('data-row'), 10);
                var idx = parseInt(target.getAttribute('data-index'), 10);
                if (!data[rowIdx] || !data[rowIdx][idx]) return;
                data[rowIdx][idx][key] = target.value;
                sync();
                if (target.getAttribute('data-rerender') === '1') render();
            });
            list.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-action]');
                if (!btn) return;
                var action = btn.getAttribute('data-action');
                var idx = parseInt(btn.getAttribute('data-index'), 10);
                var rowAttr = btn.getAttribute('data-row');

                if (action === 'remove-row') {
                    data.splice(idx, 1);
                } else if (action === 'move-up' && idx > 0) {
                    var t = data[idx - 1]; data[idx - 1] = data[idx]; data[idx] = t;
                } else if (action === 'move-down' && idx < data.length - 1) {
                    var t2 = data[idx + 1]; data[idx + 1] = data[idx]; data[idx] = t2;
                } else if (action === 'add-item') {
                    var row = data[parseInt(rowAttr, 10)];
                    if (Array.isArray(row)) {
                        row.push({ type: 'text', html: '' });
                    }
                } else if (rowAttr !== null && action === 'remove-item') {
                    var row2 = data[parseInt(rowAttr, 10)];
                    if (Array.isArray(row2)) row2.splice(idx, 1);
                }
                render();
                sync();
            });

            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    data.push([{ type: 'text', html: '' }]);
                    render();
                    sync();
                });
            }
        })();

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
