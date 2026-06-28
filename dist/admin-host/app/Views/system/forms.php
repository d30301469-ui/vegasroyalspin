<?php

$modules = is_array($modules ?? null) ? $modules : [];
$formModules = array_filter($modules, static fn (array $module): bool => ($module['active'] ?? '') === 'forms');
$quickModules = array_slice($modules, 0, 8, true);
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Sistem · Formlar</span>
        <h1 class="hero-title">Form <span class="accent">merkezi</span></h1>
        <p class="hero-sub">Site ayarları, provider yapılandırması ve admin kayıtları bu merkezden yönetilir.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/table/create?name=site_ayarlar'), ENT_QUOTES, 'UTF-8') ?>">Site ayarı ekle</a>
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/signup'), ENT_QUOTES, 'UTF-8') ?>">Admin ekle</a>
    </div>
</section>

<div class="grid">
    <section class="col-7 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Live form</span>
                <h2 class="card-title">Tema form bileşenleri</h2>
            </div>
            <span class="badge solid">Form</span>
        </div>
        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="demo_title">Başlık</label>
                <input id="demo_title" class="input" value="<?= htmlspecialchars((string) ($site['site_name'] ?? 'Site'), ENT_QUOTES, 'UTF-8') ?> form örneği" disabled>
                <div class="field-help">Gerçek kayıt işlemleri tabloya özel create/edit sayfalarında yapılır.</div>
            </div>
            <div class="field">
                <label class="field-label" for="demo_status">Durum</label>
                <select id="demo_status" class="select" disabled>
                    <option>Aktif</option>
                </select>
            </div>
            <div class="field span-2">
                <label class="field-label" for="demo_text">Açıklama</label>
                <textarea id="demo_text" class="textarea" rows="5" disabled>Bu sayfa artık redirect değildir; tema HTML karşılığı PHP view olarak admin panelde çalışır.</textarea>
            </div>
            <label class="switch span-2">
                <input type="checkbox" checked disabled>
                <span class="track"></span>
                CSRF korumalı formlar aktif
            </label>
        </div>
    </section>

    <section class="col-5 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Form modules</span>
                <h2 class="card-title">Hızlı erişim</h2>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach ($formModules as $key => $module): ?>
                <a class="data-row" href="<?= htmlspecialchars(AdminAuth::url('/module?key=' . rawurlencode((string) $key)), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="cell-name"><?= htmlspecialchars((string) ($module['title'] ?? $key), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="badge dot info"><?= htmlspecialchars((string) ($module['table'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
            <a class="data-row" href="<?= htmlspecialchars(AdminAuth::url('/signup'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="cell-name">Admin Ekle</span>
                <span class="badge dot success">admins</span>
            </a>
        </div>
    </section>

    <section class="col-12 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Database forms</span>
                <h2 class="card-title">Tablo formları</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Modül</th><th>Tablo</th><th>İşlem</th></tr></thead>
                <tbody>
                <?php foreach ($quickModules as $key => $module): ?>
                    <tr>
                        <td class="cell-name"><?= htmlspecialchars((string) ($module['title'] ?? $key), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge"><?= htmlspecialchars((string) ($module['table'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/table/create?name=' . rawurlencode((string) ($module['table'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>">Yeni kayıt</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
