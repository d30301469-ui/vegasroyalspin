<?php

$ticket = is_array($ticket ?? null) ? $ticket : [];
$messages = is_array($messages ?? null) ? $messages : [];
$flash = trim((string) ($flash ?? ''));
$ticketId = (int) ($ticket['id'] ?? 0);
$isClosed = (string) ($ticket['status'] ?? '') === 'closed';
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
        <span class="eyebrow">Destek · #<?= $ticketId ?></span>
        <h1 class="hero-title"><?= $text($ticket['subject'] ?? 'Destek Talebi') ?></h1>
        <p class="hero-sub">
            <?= $text($ticket['username'] ?? '') ?>
            · <?= $text($statusLabel((string) ($ticket['status'] ?? ''))) ?>
            · <?= $text($ticket['category'] ?? 'general') ?>
        </p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/support/tickets')) ?>">← Listeye dön</a>
        <?php if (!$isClosed): ?>
            <form method="post" action="<?= $text(AdminAuth::url('/support/close')) ?>" style="display:inline">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                <button class="btn btn--ghost" type="submit">Talebi Kapat</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--success"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card" style="margin-bottom:16px">
    <div class="card-head"><h2 class="card-title">Mesaj Geçmişi</h2></div>
    <div class="card-body">
        <?php if ($messages === []): ?>
            <p>Henüz mesaj yok.</p>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <?php $isAdmin = (string) ($message['sender_type'] ?? '') === 'admin'; ?>
                <article style="margin-bottom:14px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:<?= $isAdmin ? 'var(--bg-muted)' : 'var(--bg-card)' ?>">
                    <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:6px">
                        <strong><?= $text($message['sender_name'] ?? ($isAdmin ? 'Admin' : 'Üye')) ?></strong>
                        <span class="muted"><?= $text($message['created_at'] ?? '') ?></span>
                    </div>
                    <div><?= nl2br($text($message['message'] ?? '')) ?></div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php if (!$isClosed): ?>
<section class="card">
    <div class="card-head"><h2 class="card-title">Yanıt Yaz</h2></div>
    <form method="post" action="<?= $text(AdminAuth::url('/support/reply')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
        <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
        <div class="field" style="margin-bottom:14px">
            <label class="field-label" for="message">Mesaj</label>
            <textarea id="message" class="input textarea" name="message" rows="5" required placeholder="Üyeye gönderilecek yanıt..."></textarea>
        </div>
        <div class="form-actions">
            <button class="btn btn--primary" type="submit">Yanıt Gönder</button>
        </div>
    </form>
</section>
<?php endif; ?>
