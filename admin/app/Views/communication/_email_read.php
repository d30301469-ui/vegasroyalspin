<?php

$message = is_array($message ?? null) ? $message : [];
$messageOk = !empty($messageOk);
$messageError = trim((string) ($messageError ?? ''));

$htmlBody = trim((string) ($message['html'] ?? ''));
$textBody = trim((string) ($message['text'] ?? ''));
if ($htmlBody !== '') {
    $htmlBody = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $htmlBody) ?? $htmlBody;
    $htmlBody = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $htmlBody) ?? $htmlBody;
    $htmlBody = preg_replace('/\son\w+\s*=\s*("|\').*?\1/iu', '', $htmlBody) ?? $htmlBody;
}
?>
<style>
.email-read-meta{display:grid;gap:8px;margin:0 0 14px;padding:12px 14px;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:rgba(0,0,0,.02);font-size:13px;line-height:1.5}
.email-read-meta strong{display:inline-block;min-width:64px;color:#667085}
.email-read-body{padding:14px;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:#fff;overflow:auto;max-height:min(58vh,640px);font-size:14px;line-height:1.65;word-break:break-word}
.email-read-body img{max-width:100%;height:auto}
.email-read-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}
</style>

<?php if ($messageError !== ''): ?>
    <div class="alert alert--danger" style="display:block;margin-bottom:12px;white-space:pre-wrap;">
        <?= htmlspecialchars($messageError, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($messageOk && $message !== []): ?>
    <div class="email-read-meta">
        <div><strong>Konu</strong> <?= htmlspecialchars((string) ($message['subject'] ?? '(konu yok)'), ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Kimden</strong> <?= htmlspecialchars((string) ($message['from'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Kime</strong> <?= htmlspecialchars((string) ($message['to'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Tarih</strong> <?= htmlspecialchars((string) ($message['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <div class="email-read-body">
        <?php if ($htmlBody !== ''): ?>
            <?= $htmlBody ?>
        <?php elseif ($textBody !== ''): ?>
            <pre style="white-space:pre-wrap;word-break:break-word;font-family:Arial,Helvetica,sans-serif;margin:0;"><?= htmlspecialchars($textBody, ENT_QUOTES, 'UTF-8') ?></pre>
        <?php else: ?>
            <p style="margin:0;color:#667085;">Bu mesajda görüntülenebilir içerik bulunamadı.</p>
        <?php endif; ?>
    </div>

    <div class="email-read-actions">
        <button class="btn btn--primary" type="button" data-admin-modal-close>Kapat</button>
    </div>
<?php elseif ($messageError === ''): ?>
    <p class="field-help">Mesaj yüklenemedi.</p>
    <div class="email-read-actions">
        <button class="btn btn--ghost" type="button" data-admin-modal-close>Kapat</button>
    </div>
<?php endif; ?>
