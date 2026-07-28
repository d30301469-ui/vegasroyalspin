<?php

$message = is_array($message ?? null) ? $message : [];
$messageOk = !empty($messageOk);
$messageError = trim((string) ($messageError ?? ''));
$emailSection = 'inbox';

$htmlBody = trim((string) ($message['html'] ?? ''));
$textBody = trim((string) ($message['text'] ?? ''));
if ($htmlBody !== '') {
    $htmlBody = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $htmlBody) ?? $htmlBody;
    $htmlBody = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $htmlBody) ?? $htmlBody;
    $htmlBody = preg_replace('/\son\w+\s*=\s*("|\').*?\1/iu', '', $htmlBody) ?? $htmlBody;
}
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">E-posta <span class="accent">oku</span></h1>
        <p class="hero-sub"><?= htmlspecialchars((string) ($message['subject'] ?? 'Mesaj'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email/inbox'), ENT_QUOTES, 'UTF-8') ?>">Gelen kutusuna dön</a>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($messageError !== ''): ?>
    <div class="alert alert--danger" style="display:block;margin-bottom:12px;white-space:pre-wrap;">
        <?= htmlspecialchars($messageError, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($messageOk && $message !== []): ?>
<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Mesaj</span>
            <h2 class="card-title"><?= htmlspecialchars((string) ($message['subject'] ?? '(konu yok)'), ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
    </div>
    <div style="padding:8px 4px 16px;display:grid;gap:8px;font-size:14px;">
        <div><strong>Kimden:</strong> <?= htmlspecialchars((string) ($message['from'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Kime:</strong> <?= htmlspecialchars((string) ($message['to'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Tarih:</strong> <?= htmlspecialchars((string) ($message['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <hr style="border:none;border-top:1px solid rgba(0,0,0,.08);margin:0 0 16px;">
    <?php if ($htmlBody !== ''): ?>
        <div class="email-html-body" style="padding:8px;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:10px;overflow:auto;max-width:100%;">
            <?= $htmlBody ?>
        </div>
    <?php elseif ($textBody !== ''): ?>
        <pre style="white-space:pre-wrap;word-break:break-word;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;margin:0;padding:8px;"><?= htmlspecialchars($textBody, ENT_QUOTES, 'UTF-8') ?></pre>
    <?php else: ?>
        <p class="field-help">Bu mesajda görüntülenebilir içerik bulunamadı.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
