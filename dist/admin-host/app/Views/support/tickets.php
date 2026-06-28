<?php

$tickets = is_array($tickets ?? null) ? $tickets : [];
$flash = trim((string) ($flash ?? ''));
$status = trim((string) ($status ?? ''));
$page = max(1, (int) ($page ?? 1));
$total = max(0, (int) ($total ?? 0));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$statusLabel = static fn (string $value): string => match ($value) {
    'open' => 'Açık',
    'answered' => 'Yanıtlandı',
    'closed' => 'Kapalı',
    default => $value,
};
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">İletişim · Destek</span>
        <h1 class="hero-title">Destek <span class="accent">Talepleri</span></h1>
        <p class="hero-sub">Üye destek ticket'larını görüntüleyin, yanıtlayın ve kapatın.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/support/tickets')) ?>">Tümü</a>
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/support/tickets?status=open')) ?>">Açık</a>
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/support/tickets?status=answered')) ?>">Yanıtlandı</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--success"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <h2 class="card-title"><?= $total ?> talep<?= $status !== '' ? ' · ' . $text($statusLabel($status)) : '' ?></h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Konu</th>
                    <th>Üye</th>
                    <th>Durum</th>
                    <th>Öncelik</th>
                    <th>Güncelleme</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tickets === []): ?>
                    <tr><td colspan="7">Kayıt bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php $id = (int) ($ticket['id'] ?? 0); ?>
                        <tr>
                            <td>#<?= $id ?></td>
                            <td><?= $text($ticket['subject'] ?? '') ?></td>
                            <td>
                                <?= $text($ticket['username'] ?? '') ?>
                                <?php if (!empty($ticket['email'])): ?>
                                    <span class="muted"><?= $text($ticket['email']) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php
                            $ticketBadge = match ((string) ($ticket['status'] ?? '')) {
                                'open'     => 'warning',
                                'answered' => 'info',
                                'closed'   => 'success',
                                default    => 'primary',
                            };
                            ?><td><span class="badge <?= $ticketBadge ?>"><?= $text($statusLabel((string) ($ticket['status'] ?? ''))) ?></span></td>
                            <td><?= $text($ticket['priority'] ?? 'normal') ?></td>
                            <td><?= $text($ticket['updated_at'] ?? '') ?></td>
                            <td><a class="btn btn--ghost btn--sm" href="<?= $text(AdminAuth::url('/support/ticket?id=' . $id)) ?>">Görüntüle</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
