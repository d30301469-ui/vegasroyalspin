<?php

$mailLogs = is_array($mailLogs ?? null) ? $mailLogs : [];
$emailSection = 'sent';
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">Gönderilen <span class="accent">e-posta</span></h1>
        <p class="hero-sub">SMTP ile gönderilen maillerin kaydı.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/email/send'), ENT_QUOTES, 'UTF-8') ?>">E-posta gönder</a>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Giden</span>
            <h2 class="card-title">Gönderim logları <span class="badge primary"><?= count($mailLogs) ?></span></h2>
        </div>
    </div>
    <?php if ($mailLogs === []): ?>
        <p class="field-help" style="padding:16px;">Henüz gönderilmiş e-posta kaydı yok.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table admin-compact-table">
                <thead>
                    <tr>
                        <th>Alıcı</th>
                        <th>Konu</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mailLogs as $log): ?>
                        <?php
                        $status = strtolower(trim((string) ($log['status'] ?? '')));
                        if ($status === 'sent' || $status === 'success' || $status === 'ok') {
                            $statusBadge = 'dot success';
                            $statusLabel = 'Başarılı';
                        } elseif ($status === 'failed' || $status === 'error') {
                            $statusBadge = 'dot danger';
                            $statusLabel = 'Hata';
                        } elseif ($status === 'not_configured') {
                            $statusBadge = 'dot warning';
                            $statusLabel = 'Yapılandırılmadı';
                        } elseif ($status === 'queued') {
                            $statusBadge = 'dot info';
                            $statusLabel = 'Kuyrukta';
                        } else {
                            $statusBadge = 'dot warning';
                            $statusLabel = $status !== '' ? $status : 'Bilinmiyor';
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($log['to_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($log['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge <?= htmlspecialchars($statusBadge, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
