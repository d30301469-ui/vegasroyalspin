<?php

$notifications = is_array($notifications ?? null) ? $notifications : [];
$flash = trim((string) ($flash ?? ''));
$userId = max(0, (int) ($userId ?? 0));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">İletişim · Bildirimler</span>
        <h1 class="hero-title">Üye <span class="accent">Bildirimleri</span></h1>
        <p class="hero-sub">Üyelere uygulama içi bildirim gönderin ve son kayıtları görüntüleyin.</p>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--success"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card" style="margin-bottom:16px">
    <div class="card-head"><h2 class="card-title">Yeni Bildirim Gönder</h2></div>
    <form method="post" action="<?= $text(AdminAuth::url('/notifications/send')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
        <div class="form-grid" style="grid-template-columns:1fr 1fr">
            <div class="field">
                <label class="field-label" for="user_id">Üye ID</label>
                <input id="user_id" class="input" name="user_id" type="number" min="1" required value="<?= $userId > 0 ? $userId : '' ?>" placeholder="123">
            </div>
            <div class="field">
                <label class="field-label" for="type">Tür</label>
                <select id="type" class="select" name="type">
                    <option value="info">info</option>
                    <option value="success">success</option>
                    <option value="warning">warning</option>
                    <option value="promo">promo</option>
                    <option value="support">support</option>
                </select>
            </div>
        </div>
        <div class="field" style="margin-top:12px">
            <label class="field-label" for="title">Başlık</label>
            <input id="title" class="input" name="title" type="text" required maxlength="190" placeholder="Bildirim başlığı">
        </div>
        <div class="field" style="margin-top:12px">
            <label class="field-label" for="body">Mesaj</label>
            <textarea id="body" class="input textarea" name="body" rows="4" placeholder="Opsiyonel açıklama"></textarea>
        </div>
        <div class="field" style="margin-top:12px">
            <label class="field-label" for="action_url">Aksiyon URL (opsiyonel)</label>
            <input id="action_url" class="input" name="action_url" type="text" maxlength="700" placeholder="/promotions">
        </div>
        <div class="form-actions">
            <button class="btn btn--primary" type="submit">Bildirim Gönder</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <h2 class="card-title">Son Bildirimler<?= $userId > 0 ? ' · user #' . $userId : '' ?></h2>
        <?php if ($userId > 0): ?>
            <a class="btn btn--ghost btn--sm" href="<?= $text(AdminAuth::url('/notifications')) ?>">Filtreyi temizle</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Üye</th>
                    <th>Tür</th>
                    <th>Başlık</th>
                    <th>Okundu</th>
                    <th>Tarih</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($notifications === []): ?>
                    <tr><td colspan="6">Kayıt bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($notifications as $row): ?>
                        <tr>
                            <td><?= (int) ($row['id'] ?? 0) ?></td>
                            <td>
                                #<?= (int) ($row['user_id'] ?? 0) ?>
                                <?= $text($row['username'] ?? '') ?>
                            </td>
                            <?php
                            $notifBadge = match ((string) ($row['type'] ?? 'info')) {
                                'success' => 'success',
                                'warning' => 'warning',
                                'promo'   => 'purple',
                                'support' => 'warning',
                                default   => 'info',
                            };
                            ?>
                            <td><span class="badge <?= $notifBadge ?>"><?= $text($row['type'] ?? 'info') ?></span></td>
                            <td><?= $text($row['title'] ?? '') ?></td>
                            <td><?= !empty($row['is_read']) ? '<span class="badge success">Evet</span>' : '<span class="badge">Hayır</span>' ?></td>
                            <td><?= $text($row['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
