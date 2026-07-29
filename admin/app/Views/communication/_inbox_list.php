<?php

$messages = is_array($messages ?? null) ? $messages : [];
$inboxOk = !empty($inboxOk);
$inboxError = trim((string) ($inboxError ?? ''));
?>
<?php if ($inboxError !== ''): ?>
    <div class="alert alert--danger" style="display:block;margin-bottom:12px;white-space:pre-wrap;">
        <?= htmlspecialchars($inboxError, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">IMAP INBOX</span>
            <h2 class="card-title">
                Gelen kutusu
                <span class="badge primary"><?= count($messages) ?></span>
            </h2>
        </div>
        <?php if ($inboxOk): ?>
            <span class="badge dot success">Bağlı</span>
        <?php else: ?>
            <span class="badge dot danger">Bağlantı yok</span>
        <?php endif; ?>
    </div>

    <?php if ($messages === []): ?>
        <p class="field-help" style="padding:16px;">
            <?= $inboxOk ? 'Gelen kutusunda mesaj yok.' : 'Mesajlar listelenemedi. Ayarları ve php-imap eklentisini kontrol edin.' ?>
        </p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table admin-compact-table">
                <thead>
                    <tr>
                        <th>Durum</th>
                        <th>Gönderen</th>
                        <th>Konu</th>
                        <th>Özet</th>
                        <th>Tarih</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                        <?php $uid = (int) ($message['uid'] ?? 0); ?>
                        <tr>
                            <td><?= empty($message['seen']) ? 'Yeni' : 'Okundu' ?></td>
                            <td><?= htmlspecialchars((string) ($message['from'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($message['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($message['preview'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($message['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($uid > 0): ?>
                                    <?php
                                    $viewUrl = AdminAuth::url('/email/inbox/view?uid=' . $uid);
                                    $subjectTitle = trim((string) ($message['subject'] ?? 'E-posta'));
                                    ?>
                                    <a
                                        class="btn btn--primary"
                                        href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        data-admin-modal-url="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        data-admin-modal-title="<?= htmlspecialchars($subjectTitle !== '' ? $subjectTitle : 'E-posta oku', ENT_QUOTES, 'UTF-8') ?>"
                                    >Oku</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
