<?php

$emailSection = trim((string) ($emailSection ?? 'inbox'));
$navItems = [
    'send' => ['label' => 'E-posta gönder', 'url' => '/email/send'],
    'inbox' => ['label' => 'Gelen e-postalar', 'url' => '/email/inbox'],
    'sent' => ['label' => 'Gönderilen e-posta', 'url' => '/email/sent'],
    'settings' => ['label' => 'Ayarlar', 'url' => '/email/settings'],
    'templates' => ['label' => 'E-posta şablonları', 'url' => '/email/templates'],
];
?>
<style>
.email-subnav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px;padding:10px;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:rgba(0,0,0,.02)}
.email-subnav a{display:inline-flex;align-items:center;padding:9px 14px;border-radius:999px;font-size:13px;font-weight:700;color:inherit;text-decoration:none;opacity:.75}
.email-subnav a:hover{opacity:1;background:rgba(133,15,131,.08)}
.email-subnav a.is-active{opacity:1;background:#850f83;color:#fff}
</style>
<nav class="email-subnav" aria-label="E-posta menüsü">
    <?php foreach ($navItems as $key => $item): ?>
        <a class="<?= $emailSection === $key ? 'is-active' : '' ?>" href="<?= htmlspecialchars(AdminAuth::url((string) $item['url']), ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?>
        </a>
    <?php endforeach; ?>
</nav>
