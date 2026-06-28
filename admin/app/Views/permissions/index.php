<?php

$admins = is_array($admins ?? null) ? $admins : [];
$permissionGroups = is_array($permissionGroups ?? null) ? $permissionGroups : [];
$grants = is_array($grants ?? null) ? $grants : [];
$selectedAdminId = max(0, (int) ($selectedAdminId ?? 0));
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$selectedAdmin = null;
foreach ($admins as $admin) {
    if ((int) ($admin['id'] ?? 0) === $selectedAdminId) {
        $selectedAdmin = $admin;
        break;
    }
}
$totalPermissions = 0;
$grantedCount = 0;
foreach ($permissionGroups as $group) {
    foreach ((array) ($group['items'] ?? []) as $item) {
        $totalPermissions++;
        if (!empty($grants[(string) ($item['key'] ?? '')])) {
            $grantedCount++;
        }
    }
}
?>

<style>
    .permissions-page { display:flex; flex-direction:column; gap:18px; }
    .permissions-summary { display:grid; grid-template-columns:repeat(3, minmax(180px, 1fr)); gap:14px; }
    .permissions-stat { background:var(--bg-muted); border:1px solid var(--border-soft); border-radius:16px; padding:16px; }
    .permissions-stat span { display:block; color:var(--t-light); font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .permissions-stat strong { display:block; color:var(--t-base); font-family:Inter Tight, Inter, sans-serif; font-size:24px; line-height:1.1; margin-top:6px; }
    .permissions-toolbar { align-items:end; display:grid; grid-template-columns:minmax(260px, 420px) auto 1fr; gap:12px; }
    .permission-groups { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:18px; }
    .permission-group { min-width:0; }
    .permission-list { display:flex; flex-direction:column; gap:10px; }
    .permission-item { align-items:flex-start; border:1px solid var(--border-soft); border-radius:14px; display:grid; gap:12px; grid-template-columns:38px 1fr auto; padding:14px; transition:border-color .16s, background .16s; }
    .permission-item:hover { background:var(--bg-hover); border-color:var(--border); }
    .permission-icon { background:var(--primary-soft); border-radius:10px; color:var(--primary); display:grid; height:38px; place-items:center; width:38px; }
    .permission-icon svg { fill:none; height:18px; stroke:currentColor; stroke-linecap:round; stroke-linejoin:round; stroke-width:1.75; width:18px; }
    .permission-title { color:var(--t-base); font-weight:700; line-height:1.25; }
    .permission-desc { color:var(--t-muted); font-size:12px; line-height:1.45; margin-top:4px; }
    .permission-key { color:var(--t-light); font-family:JetBrains Mono, monospace; font-size:10px; letter-spacing:.04em; margin-top:6px; }
    .permissions-empty { color:var(--t-muted); padding:24px; text-align:center; }
    @media (max-width:1080px) {
        .permission-groups { grid-template-columns:1fr; }
        .permissions-summary { grid-template-columns:1fr; }
        .permissions-toolbar { grid-template-columns:1fr; }
    }
</style>

<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">Admin · Yetkiler</span>
        <h1 class="hero-title">Admin <span class="accent">Yetkileri</span></h1>
        <p class="hero-sub">Adminlerin panelde hangi bölümlere erişebileceğini tek ekrandan yönetin. Yetkiler sidebar yapısına göre gruplanır ve kayıtlar <code>admin_permissions</code> tablosunda tutulur.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/module?key=admins'), ENT_QUOTES, 'UTF-8') ?>">Adminleri Gör</a>
        <button class="btn btn--primary" type="submit" form="permissionsForm">Yetkileri Kaydet</button>
    </div>
</div>

<div class="permissions-page">
    <section class="card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Admin seçimi</span>
                <h2 class="card-title">Yetkisi düzenlenecek admin</h2>
            </div>
            <span class="badge primary"><?= $text($selectedAdmin['role'] ?? 'rol yok') ?></span>
        </div>

        <form class="permissions-toolbar" method="get" action="<?= htmlspecialchars(AdminAuth::url('/permissions'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="field">
                <label class="field-label" for="adminPermissionSelect">Admin hesabı</label>
                <select id="adminPermissionSelect" class="select" name="admin_id" onchange="this.form.submit()">
                    <?php foreach ($admins as $admin): ?>
                        <?php $adminId = (int) ($admin['id'] ?? 0); ?>
                        <option value="<?= $adminId ?>" <?= $adminId === $selectedAdminId ? 'selected' : '' ?>>
                            #<?= $adminId ?> · <?= $text($admin['username'] ?? 'Admin') ?> · <?= $text($admin['email'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn--ghost" type="submit">Admini Aç</button>
            <div class="field-help">Bu ekran erişim kayıtlarını düzenler. Kritik aksiyonlar için backend tarafı yetki kontrolü eklenerek bu tablo kullanılabilir.</div>
        </form>
    </section>

    <section class="permissions-summary">
        <div class="permissions-stat"><span>Seçili admin</span><strong><?= $text($selectedAdmin['username'] ?? 'Admin seçilmedi') ?></strong></div>
        <div class="permissions-stat"><span>Açık yetki</span><strong><?= $grantedCount ?> / <?= $totalPermissions ?></strong></div>
        <div class="permissions-stat"><span>Yetki grubu</span><strong><?= count($permissionGroups) ?></strong></div>
    </section>

    <?php if ($selectedAdminId <= 0 || $admins === []): ?>
        <section class="card"><div class="permissions-empty">Önce bir admin hesabı oluşturun veya seçin.</div></section>
    <?php else: ?>
        <form id="permissionsForm" method="post" action="<?= htmlspecialchars(AdminAuth::url('/permissions'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="admin_id" value="<?= $text($selectedAdminId) ?>">

            <div class="permission-groups">
                <?php foreach ($permissionGroups as $group): ?>
                    <section class="card permission-group">
                        <div class="card-head">
                            <div class="card-title-wrap">
                                <span class="eyebrow"><?= $text($group['caption'] ?? 'Yetki grubu') ?></span>
                                <h2 class="card-title"><?= $text($group['label'] ?? 'Admin') ?></h2>
                            </div>
                        </div>
                        <div class="permission-list">
                            <?php foreach ((array) ($group['items'] ?? []) as $item): ?>
                                <?php
                                $key = (string) ($item['key'] ?? '');
                                $isGranted = !empty($grants[$key]);
                                ?>
                                <label class="permission-item" for="permission_<?= $text($key) ?>">
                                    <span class="permission-icon"><svg viewBox="0 0 24 24"><?= (string) ($item['icon'] ?? '') ?></svg></span>
                                    <span>
                                        <span class="permission-title"><?= $text($item['text'] ?? $key) ?></span>
                                        <span class="permission-desc"><?= $text($item['description'] ?? '') ?></span>
                                        <span class="permission-key"><?= $text($key) ?> · <?= $text($item['url'] ?? '#') ?></span>
                                    </span>
                                    <span class="switch">
                                        <input id="permission_<?= $text($key) ?>" type="checkbox" name="permissions[]" value="<?= $text($key) ?>" <?= $isGranted ? 'checked' : '' ?>>
                                        <span class="track"></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <span class="badge dot warning">Kapalı switch olan sayfalar bu admin için yetkisiz kabul edilir.</span>
                <span class="spacer"></span>
                <button class="btn btn--primary" type="submit">Yetkileri Kaydet</button>
            </div>
        </form>
    <?php endif; ?>
</div>
</section>
