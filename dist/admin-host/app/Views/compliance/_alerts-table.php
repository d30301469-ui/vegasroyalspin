<?php

$alerts = is_array($alerts ?? null) ? $alerts : [];
$resolveUrl = (string) ($resolveUrl ?? '');
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$severityClass = static fn (string $value): string => match ($value) {
    'critical', 'high' => 'danger',
    'medium' => 'warning',
    default => 'info',
};
?>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Başlık</th>
                <th>Üye</th>
                <th>Önem</th>
                <th>Durum</th>
                <th>Tarih</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($alerts === []): ?>
                <tr><td colspan="7">Kayıt bulunamadı.</td></tr>
            <?php else: ?>
                <?php foreach ($alerts as $alert): ?>
                    <?php $id = (int) ($alert['id'] ?? 0); ?>
                    <?php $isOpen = (string) ($alert['status'] ?? '') === 'open'; ?>
                    <tr>
                        <td>#<?= $id ?></td>
                        <td>
                            <strong><?= $text($alert['title'] ?? '') ?></strong>
                            <?php if (!empty($alert['description'])): ?>
                                <div class="muted"><?= $text(substr((string) $alert['description'], 0, 120)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($alert['user_id'])): ?>
                                #<?= (int) $alert['user_id'] ?> <?= $text($alert['username'] ?? '') ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $text($severityClass((string) ($alert['severity'] ?? 'medium'))) ?>"><?= $text($alert['severity'] ?? 'medium') ?></span></td>
                        <?php
                        $alertStatusClass = match ((string) ($alert['status'] ?? 'open')) {
                            'open'     => 'warning',
                            'resolved' => 'success',
                            'ignored'  => 'primary',
                            default    => 'primary',
                        };
                        ?><td><span class="badge <?= $alertStatusClass ?>"><?= $text($alert['status'] ?? '') ?></span></td>
                        <td><?= $text($alert['created_at'] ?? '') ?></td>
                        <td>
                            <?php if ($isOpen && $resolveUrl !== ''): ?>
                                <form method="post" action="<?= $text($resolveUrl) ?>" style="display:flex;gap:6px;align-items:center">
                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <input class="input" type="text" name="note" placeholder="Not" maxlength="500" style="max-width:140px;min-height:30px;padding:4px 8px;font-size:11px">
                                    <button class="btn btn--ghost btn--sm" type="submit">Çöz</button>
                                </form>
                            <?php elseif (!$isOpen): ?>
                                <span class="muted"><?= $text($alert['resolved_by'] ?? '') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
