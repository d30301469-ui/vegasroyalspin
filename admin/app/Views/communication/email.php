<?php

$messages = is_array($messages ?? null) ? $messages : [];
$mailLogs = is_array($mailLogs ?? null) ? $mailLogs : [];
$settings = is_array($settings ?? null) ? $settings : [];
$unread = count(array_filter($messages, static fn (array $row): bool => !empty($row['is_active'])));
?>
<section class="admin-surface">
<div class="hero mail-hero">
    <div class="hero-text">
        <span class="eyebrow" id="heroDate"><?= htmlspecialchars(date('l · F d · Y'), ENT_QUOTES, 'UTF-8') ?></span>
        <h1 class="hero-title">Gelen Kutusu · <span class="accent"><?= $unread ?> aktif</span></h1>
        <p class="hero-sub">Üye gelen kutusu, outbound mail logları ve SMTP ayarları tek iletişim ekranında görüntülenir.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg> Yenile</a>
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/compose'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/></svg> Mesaj Yaz</a>
    </div>
</div>

<section class="mail-shell" aria-label="Email">
    <aside class="mail-rail">
        <a class="mail-compose" href="<?= htmlspecialchars(AdminAuth::url('/compose'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/></svg> Mesaj Yaz</a>
        <div class="mail-rail-section">
            <div class="mail-rail-label">Klasörler</div>
            <a class="mail-folder is-active" href="<?= htmlspecialchars(AdminAuth::url('/table?name=member_inbox_messages'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg> <span class="mail-folder-name">Gelen Kutusu</span> <span class="mail-folder-count is-strong"><?= count($messages) ?></span></a>
            <a class="mail-folder" href="<?= htmlspecialchars(AdminAuth::url('/table?name=mail_outbound_log'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> <span class="mail-folder-name">Gönderilen</span> <span class="mail-folder-count"><?= count($mailLogs) ?></span></a>
            <a class="mail-folder" href="<?= htmlspecialchars(AdminAuth::url('/email/settings'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg> <span class="mail-folder-name">Ayarlar</span></a>
        </div>
        <div class="mail-rail-storage">
            <div class="mail-storage-head"><span>SMTP</span> <span class="num"><?= !empty($settings['enabled']) || !empty($settings['mail_enabled']) ? 'enabled' : 'passive' ?></span></div>
            <div class="mail-storage-bar"><div class="mail-storage-bar-fill" style="width:<?= !empty($settings) ? '70' : '10' ?>%"></div></div>
            <div class="mail-storage-foot"><?= htmlspecialchars((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? 'mail ayarı yok'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </aside>

    <section class="mail-list">
        <div class="mail-list-head">
            <div class="mail-list-toptools"><div class="mail-list-title">Gelen Kutusu<span class="meta"><?= $unread ?> AKTİF · <?= count($messages) ?> TOPLAM</span></div></div>
            <div class="mail-search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg> <input type="text" placeholder="Mesaj ara..."></div>
        </div>
        <div class="mail-tabs"><a class="mail-tab is-active" href="#">Birincil <span class="num"><?= count($messages) ?></span></a> <a class="mail-tab" href="#">Gönderim <span class="num"><?= count($mailLogs) ?></span></a></div>
        <div class="mail-list-scroll">
            <?php foreach ($messages as $index => $message): ?>
                <article class="mail-row <?= !empty($message['is_active']) ? 'is-unread' : '' ?> <?= $index === 0 ? 'is-active' : '' ?>">
                    <div class="mail-row-avatar ma-<?= ($index % 6) + 1 ?>"><?= htmlspecialchars(strtoupper(substr((string) ($message['title'] ?? 'MS'), 0, 2)), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mail-row-body">
                        <div class="mail-row-top"><div class="mail-row-from"><?= htmlspecialchars((string) ($message['title'] ?? 'Mesaj'), ENT_QUOTES, 'UTF-8') ?></div><div class="mail-row-time"><?= htmlspecialchars((string) ($message['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                        <div class="mail-row-subject"><?= htmlspecialchars((string) ($message['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="mail-row-preview"><?= htmlspecialchars(substr((string) ($message['body'] ?? ''), 0, 140), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="mail-row-tags"><span class="mail-tag work">priority <?= (int) ($message['priority'] ?? 0) ?></span></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="mail-reader">
        <div class="reader-toolbar"><div class="reader-tools-group"><a class="mail-tool" href="<?= htmlspecialchars(AdminAuth::url('/table?name=member_inbox_messages'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Open table"><svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></a></div></div>
        <div class="reader-scroll">
            <header class="reader-head"><h1 class="reader-subject">Üye Gelen Kutusu</h1><div class="reader-meta-row"><span class="mail-tag team">member_inbox_messages</span><span class="mail-tag finance">mail_outbound_log</span></div></header>
            <article class="reader-card"><div class="reader-body"><p>Bu ekran üye mesajları ve mail kayıtlarını gösterir.</p><p>Yeni mesaj oluşturmak için Mesaj Yaz ekranını kullanabilirsiniz.</p></div></article>
        </div>
    </section>
</section>
</section>
