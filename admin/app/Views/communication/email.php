<?php

$messages = is_array($messages ?? null) ? $messages : [];
$emailSection = 'inbox';
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">Gelen <span class="accent">e-postalar</span></h1>
        <p class="hero-sub">Üye gelen kutusundaki mesajlar.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/email/send'), ENT_QUOTES, 'UTF-8') ?>">E-posta gönder</a>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Gelen</span>
            <h2 class="card-title">Mesaj listesi <span class="badge primary"><?= count($messages) ?></span></h2>
        </div>
    </div>
    <?php if ($messages === []): ?>
        <p class="field-help" style="padding:16px;">Henüz gelen mesaj yok.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table admin-compact-table">
                <thead>
                    <tr>
                        <th>Başlık</th>
                        <th>Özet</th>
                        <th>Tarih</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($message['title'] ?? 'Mesaj'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(substr((string) ($message['body'] ?? ''), 0, 120), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($message['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= !empty($message['is_active']) ? 'Aktif' : 'Pasif' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
