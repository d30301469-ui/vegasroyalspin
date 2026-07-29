<?php

$settings = is_array($settings ?? null) ? $settings : [];
$flash = trim((string) ($flash ?? ''));
$emailSection = 'templates';
$resetPreviewHtml = (string) ($resetPreviewHtml ?? '');
$welcomePreviewHtml = (string) ($welcomePreviewHtml ?? '');
$depositApprovedPreviewHtml = (string) ($depositApprovedPreviewHtml ?? '');
$withdrawApprovedPreviewHtml = (string) ($withdrawApprovedPreviewHtml ?? '');
$previewUrl = (string) ($previewUrl ?? AdminAuth::url('/email/templates/preview'));

$placeholders = [
    '{{MEMBER_NAME}}' => 'Üyenin adı soyadı',
    '{{COMPANY_NAME}}' => 'Şirket / marka adı',
    '{{AMOUNT}}' => 'İşlem tutarı (yatırım/çekim)',
    '{{HEADING}}' => 'Mail başlığı',
    '{{BODY_HTML}}' => 'Mail metni',
    '{{CTA_LABEL}}' => 'Buton yazısı',
    '{{CTA_URL}}' => 'Buton bağlantısı',
    '{{SUPPORT_EMAIL}}' => 'Destek e-postası',
    '{{COMPANY_ADDRESS_HTML}}' => 'Footer adresi',
    '{{LOGO_HTML}}' => 'Logo alanı',
    '{{YEAR}}' => 'Yıl',
];

$templates = [
    [
        'key' => 'reset',
        'field' => 'reset_template_html',
        'title' => 'Şifre sıfırlama',
        'trigger' => 'Üye şifre sıfırlama talebi oluşturduğunda',
        'help' => 'Boş bırakırsan sistem varsayılan şablonu kullanılır.',
    ],
    [
        'key' => 'welcome',
        'field' => 'welcome_template_html',
        'title' => 'Kayıt başarılı',
        'trigger' => 'Yeni üye kaydı tamamlandığında',
        'help' => 'Yeni üye kaydı tamamlandığında otomatik gönderilir.',
    ],
    [
        'key' => 'deposit_approved',
        'field' => 'deposit_approved_template_html',
        'title' => 'Yatırım onaylandı',
        'trigger' => 'Para yatırma işlemi onaylandığında',
        'help' => 'Tutar için {{AMOUNT}} kullanabilirsiniz.',
    ],
    [
        'key' => 'withdraw_approved',
        'field' => 'withdraw_approved_template_html',
        'title' => 'Çekim tamamlandı',
        'trigger' => 'Para çekme işlemi tamamlandığında',
        'help' => 'Tutar için {{AMOUNT}} kullanabilirsiniz.',
    ],
];

$previewMap = [
    'reset' => $resetPreviewHtml,
    'welcome' => $welcomePreviewHtml,
    'deposit_approved' => $depositApprovedPreviewHtml,
    'withdraw_approved' => $withdrawApprovedPreviewHtml,
];
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">E-posta <span class="accent">şablonları</span></h1>
        <p class="hero-sub">Üyeye otomatik giden e-postaların içeriği ve görünümü.</p>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($flash !== ''): ?>
    <div class="alert <?= stripos($flash, 'kaydedilemedi') !== false ? 'alert--danger' : 'alert--success' ?>" style="display:block;margin-bottom:12px;white-space:pre-wrap;">
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<style>
.tpl-list-table{width:100%;border-collapse:collapse}
.tpl-list-table th{
    text-align:left;padding:10px 12px;border-bottom:1px solid var(--border);
    color:var(--t-light);font-family:JetBrains Mono,monospace;font-size:10px;
    font-weight:500;letter-spacing:.14em;text-transform:uppercase;
}
.tpl-list-table td{padding:14px 12px;border-bottom:1px solid var(--border-soft);vertical-align:middle}
.tpl-list-table tr:last-child td{border-bottom:0}
.tpl-list-table tbody tr:hover td{background:var(--bg-hover)}
.tpl-list-name{color:var(--t-base);font-size:14px;font-weight:700;margin:0 0 3px}
.tpl-list-trigger{color:var(--t-muted);font-size:12.5px;margin:0;line-height:1.4}
.tpl-list-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
.tpl-hint-list{margin:8px 0 0;padding:0;list-style:none;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:4px 16px}
.tpl-hint-list li{font-size:12px;color:var(--t-muted)}
.tpl-hint-list code{font-size:12px}
.tpl-modal-backdrop{
    position:fixed;inset:0;z-index:130;display:none;place-items:center;padding:20px;
    background:rgba(15,23,42,.52);backdrop-filter:blur(8px);
}
.tpl-modal-backdrop.is-open{display:grid}
.tpl-modal{
    width:min(920px,100%);max-height:min(90vh,960px);display:flex;flex-direction:column;
    overflow:hidden;border:1px solid var(--border);border-radius:16px;background:var(--bg-card);
    box-shadow:0 24px 80px rgba(0,0,0,.28);
}
.tpl-modal--edit{width:min(780px,100%)}
.tpl-modal-head{
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    border-bottom:1px solid var(--border);padding:14px 16px;flex-shrink:0;
}
.tpl-modal-head h2{
    margin:0;color:var(--t-base);font-family:'Inter Tight',Inter,sans-serif;font-size:16px;font-weight:800;
}
.tpl-modal-actions{display:flex;align-items:center;gap:8px}
.tpl-modal-body{overflow:auto;padding:16px}
.tpl-modal-body--preview{padding:0;overflow:hidden;background:#0a0719;min-height:420px}
.tpl-modal-foot{
    display:flex;align-items:center;gap:8px;justify-content:flex-end;
    border-top:1px solid var(--border);padding:12px 16px;flex-shrink:0;
}
.tpl-edit-panel{display:none}
.tpl-edit-panel.is-open{display:block}
.tpl-preview-frame{display:block;width:100%;height:min(70vh,680px);border:0;background:#0a0719;transition:opacity .15s ease}
body.has-tpl-modal{overflow:hidden}
@media (max-width:720px){
    .tpl-list-actions{justify-content:flex-start}
    .tpl-list-table th:nth-child(2),.tpl-list-table td:nth-child(2){display:none}
}
</style>

<form id="mailTemplatesForm" method="post" action="<?= htmlspecialchars(AdminAuth::url('/email/templates'), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <section class="card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Marka</span>
                <h2 class="card-title">Marka bilgileri</h2>
            </div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="company_name">Şirket / marka adı</label>
                <input id="company_name" class="input" type="text" name="company_name" placeholder="Vegasroyalspin" value="<?= htmlspecialchars((string) ($settings['company_name'] ?? 'Vegasroyalspin'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="support_email">Destek e-postası</label>
                <input id="support_email" class="input" type="email" name="support_email" placeholder="support@vegasroyalspin.com" value="<?= htmlspecialchars((string) ($settings['support_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field span-2">
                <label class="field-label" for="company_address">Footer adresi</label>
                <textarea id="company_address" class="input" name="company_address" rows="2"><?= htmlspecialchars((string) ($settings['company_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="field-help">Tüm otomatik e-postaların alt kısmında görünür.</div>
            </div>
        </div>
    </section>

    <section class="card" style="margin-top:16px;">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Şablonlar</span>
                <h2 class="card-title">Otomatik e-postalar</h2>
            </div>
            <span class="badge dot info"><?= count($templates) ?> şablon</span>
        </div>

        <div class="table-scroll">
            <table class="tpl-list-table">
                <thead>
                    <tr>
                        <th>Şablon</th>
                        <th>Durum</th>
                        <th style="text-align:right;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $tpl): ?>
                        <?php
                        $key = (string) $tpl['key'];
                        $field = (string) $tpl['field'];
                        $html = trim((string) ($settings[$field] ?? ''));
                        $isCustom = $html !== '';
                        ?>
                        <tr data-tpl-row="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                            <td>
                                <p class="tpl-list-name"><?= htmlspecialchars((string) $tpl['title'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="tpl-list-trigger"><?= htmlspecialchars((string) $tpl['trigger'], ENT_QUOTES, 'UTF-8') ?></p>
                            </td>
                            <td>
                                <span class="badge <?= $isCustom ? 'dot warning' : 'dot success' ?>">
                                    <?= $isCustom ? 'Özel HTML' : 'Varsayılan' ?>
                                </span>
                            </td>
                            <td>
                                <div class="tpl-list-actions">
                                    <button
                                        class="btn btn--ghost btn--sm"
                                        type="button"
                                        data-tpl-preview="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                    >Önizle</button>
                                    <button
                                        class="btn btn--secondary btn--sm"
                                        type="button"
                                        data-tpl-edit="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                    >Düzenle</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <details style="margin-top:16px;">
            <summary class="field-label" style="cursor:pointer;">Kullanılabilir alanlar</summary>
            <ul class="tpl-hint-list">
                <?php foreach ($placeholders as $token => $label): ?>
                    <li><code><?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?></code> — <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </details>

        <div class="form-actions">
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Tümünü kaydet</button>
        </div>
    </section>

    <div
        id="tplEditModal"
        class="tpl-modal-backdrop"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tplEditModalTitle"
        hidden
    >
        <div class="tpl-modal tpl-modal--edit">
            <div class="tpl-modal-head">
                <h2 id="tplEditModalTitle">Şablon düzenle</h2>
                <div class="tpl-modal-actions">
                    <button class="admin-modal-close" type="button" id="tplEditClose" aria-label="Kapat">&times;</button>
                </div>
            </div>
            <div class="tpl-modal-body">
                <?php foreach ($templates as $tpl): ?>
                    <?php
                    $key = (string) $tpl['key'];
                    $field = (string) $tpl['field'];
                    ?>
                    <div class="tpl-edit-panel" data-tpl-edit-panel="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="field">
                            <label class="field-label" for="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>">HTML içeriği</label>
                            <textarea
                                id="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"
                                class="input"
                                name="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"
                                rows="14"
                                placeholder="Boş bırakırsan sistem varsayılan şablonu kullanılır."
                            ><?= htmlspecialchars((string) ($settings[$field] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="field-help"><?= htmlspecialchars((string) $tpl['help'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="tpl-modal-foot">
                <button class="btn btn--ghost" type="button" id="tplEditPreview">Önizle</button>
                <span class="spacer"></span>
                <button class="btn btn--ghost" type="button" data-tpl-edit-close>Vazgeç</button>
                <button class="btn btn--primary" type="submit">Kaydet</button>
            </div>
        </div>
    </div>
</form>

<div
    id="tplPreviewModal"
    class="tpl-modal-backdrop"
    role="dialog"
    aria-modal="true"
    aria-labelledby="tplPreviewModalTitle"
    hidden
>
    <div class="tpl-modal">
        <div class="tpl-modal-head">
            <h2 id="tplPreviewModalTitle">E-posta önizlemesi</h2>
            <div class="tpl-modal-actions">
                <button class="btn btn--ghost btn--sm" type="button" id="tplPreviewRefresh">Yenile</button>
                <button class="admin-modal-close" type="button" id="tplPreviewClose" aria-label="Kapat">&times;</button>
            </div>
        </div>
        <div class="tpl-modal-body tpl-modal-body--preview">
            <iframe
                id="tplPreviewFrame"
                class="tpl-preview-frame"
                title="E-posta önizlemesi"
                sandbox="allow-same-origin"
            ></iframe>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('mailTemplatesForm');
    var previewModal = document.getElementById('tplPreviewModal');
    var editModal = document.getElementById('tplEditModal');
    var frame = document.getElementById('tplPreviewFrame');
    var previewTitleEl = document.getElementById('tplPreviewModalTitle');
    var editTitleEl = document.getElementById('tplEditModalTitle');
    if (!form || !previewModal || !editModal || !frame) return;

    var previewUrl = <?= json_encode($previewUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var cache = <?= json_encode($previewMap, JSON_UNESCAPED_UNICODE) ?>;
    var titles = {
        reset: 'Şifre sıfırlama',
        welcome: 'Kayıt başarılı',
        deposit_approved: 'Yatırım onaylandı',
        withdraw_approved: 'Çekim tamamlandı'
    };
    var active = 'reset';
    var editActive = 'reset';
    var openedFromEdit = false;

    function syncBodyLock() {
        var anyOpen = previewModal.classList.contains('is-open') || editModal.classList.contains('is-open');
        document.body.classList.toggle('has-tpl-modal', anyOpen);
    }

    function openEdit(type) {
        editActive = type;
        if (editTitleEl) editTitleEl.textContent = (titles[type] || 'Şablon') + ' düzenle';
        form.querySelectorAll('[data-tpl-edit-panel]').forEach(function (panel) {
            panel.classList.toggle('is-open', panel.getAttribute('data-tpl-edit-panel') === type);
        });
        editModal.hidden = false;
        editModal.classList.add('is-open');
        syncBodyLock();
        var panel = form.querySelector('[data-tpl-edit-panel="' + type + '"]');
        var textarea = panel ? panel.querySelector('textarea') : null;
        if (textarea) setTimeout(function () { textarea.focus(); }, 80);
    }

    function closeEdit() {
        editModal.classList.remove('is-open');
        editModal.hidden = true;
        form.querySelectorAll('[data-tpl-edit-panel]').forEach(function (panel) {
            panel.classList.remove('is-open');
        });
        syncBodyLock();
    }

    function setFrameHtml(html) {
        frame.style.opacity = '0.5';
        frame.srcdoc = html || '';
        requestAnimationFrame(function () {
            frame.style.opacity = '1';
        });
    }

    function openPreview(type, fromEdit) {
        active = type;
        openedFromEdit = !!fromEdit;
        if (previewTitleEl) previewTitleEl.textContent = (titles[type] || 'E-posta') + ' önizlemesi';
        setFrameHtml(cache[type] || '');
        previewModal.hidden = false;
        previewModal.classList.add('is-open');
        syncBodyLock();
        refreshPreview(false);
    }

    function closePreview() {
        previewModal.classList.remove('is-open');
        previewModal.hidden = true;
        syncBodyLock();
        openedFromEdit = false;
    }

    function refreshPreview(forceLoading) {
        var type = active;
        var data = new FormData(form);
        data.set('template_type', type);
        if (forceLoading !== false) frame.style.opacity = '0.5';

        fetch(previewUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (response) {
                return response.text().then(function (html) {
                    if (!response.ok) throw new Error(html || ('HTTP ' + response.status));
                    cache[type] = html;
                    if (active === type) setFrameHtml(html);
                });
            })
            .catch(function (error) {
                setFrameHtml('<!DOCTYPE html><html lang="tr"><body style="font-family:Arial,sans-serif;padding:24px;color:#b00020;">Önizleme alınamadı: '
                    + String(error && error.message ? error.message : error) + '</body></html>');
            })
            .finally(function () {
                frame.style.opacity = '1';
            });
    }

    form.querySelectorAll('[data-tpl-preview]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPreview(String(btn.getAttribute('data-tpl-preview') || 'reset'), false);
        });
    });
    form.querySelectorAll('[data-tpl-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openEdit(String(btn.getAttribute('data-tpl-edit') || 'reset'));
        });
    });
    form.querySelectorAll('[data-tpl-edit-close]').forEach(function (btn) {
        btn.addEventListener('click', closeEdit);
    });

    document.getElementById('tplEditClose').addEventListener('click', closeEdit);
    document.getElementById('tplEditPreview').addEventListener('click', function () {
        openPreview(editActive, true);
    });
    document.getElementById('tplPreviewClose').addEventListener('click', closePreview);
    document.getElementById('tplPreviewRefresh').addEventListener('click', function () {
        refreshPreview(true);
    });

    editModal.addEventListener('click', function (event) {
        if (event.target === editModal) closeEdit();
    });
    previewModal.addEventListener('click', function (event) {
        if (event.target === previewModal) closePreview();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (previewModal.classList.contains('is-open')) {
            closePreview();
            return;
        }
        if (editModal.classList.contains('is-open')) {
            closeEdit();
        }
    });
})();
</script>
