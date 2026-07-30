<?php

declare(strict_types=1);

$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$users = is_array($users ?? null) ? $users : [];
$freespinGames = is_array($freespinGames ?? null) ? $freespinGames : [];
$editCampaign = is_array($editCampaign ?? null) ? $editCampaign : [];
$oldInput = is_array($oldInput ?? null) ? $oldInput : [];
$errors = is_array($errors ?? null) ? $errors : [];
$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash = trim((string) ($flash ?? ''));

$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$isEdit = $editCampaign !== [];
$hasOldInput = $oldInput !== [];

$dateTimeLocal = static function (mixed $raw): string {
    if ($raw === null || $raw === '' || $raw === 0 || $raw === '0') {
        return '';
    }
    $ts = is_numeric($raw) ? (int) $raw : strtotime((string) $raw);
    if ($ts === false || $ts <= 0) {
        return '';
    }

    return date('Y-m-d\TH:i', $ts);
};

$value = static function (string $field, mixed $default = '') use ($oldInput, $editCampaign, $hasOldInput): mixed {
    if ($hasOldInput && array_key_exists($field, $oldInput)) {
        return $oldInput[$field];
    }
    if (array_key_exists($field, $editCampaign) && $editCampaign[$field] !== null) {
        return $editCampaign[$field];
    }

    return $default;
};

$dateValue = static function (string $field, string $fallback = '') use ($value, $dateTimeLocal): string {
    $resolved = $dateTimeLocal($value($field, ''));
    return $resolved !== '' ? $resolved : $fallback;
};

$errorFor = static fn (string $field): string => (string) ($errors[$field] ?? '');
$defaultExpires = date('Y-m-d\TH:i', strtotime('+7 days'));
$selectedGame = (string) $value('game_identifier', '');
$defaultCurrency = (string) ($configRow['currency'] ?? 'USD');
$freespinsEnabled = (int) ($configRow['freespins_enabled'] ?? 1) === 1;
$bgamingActive = (int) ($configRow['is_active'] ?? 0) === 1;

$selectedUserIdsRaw = $hasOldInput ? ($oldInput['user_ids'] ?? ($oldInput['user_ids_text'] ?? '')) : '';
if (is_array($selectedUserIdsRaw)) {
    $selectedUserIdsText = implode(', ', array_map('strval', $selectedUserIdsRaw));
} else {
    $selectedUserIdsText = trim((string) $selectedUserIdsRaw);
}

$freespinCampaigns = array_values(array_filter(
    $campaigns,
    static fn (array $row): bool => (string) ($row['campaign_type'] ?? '') === 'freespin'
        && (int) ($row['active'] ?? 0) === 1
));

$statusBadgeClass = static fn (string $status): string => match ($status) {
    'active' => 'success',
    'played', 'bonus_assigned' => 'info',
    'pending' => 'warning',
    'failed', 'canceled', 'expired' => 'danger',
    default => '',
};

$recentUsers = array_slice($users, 0, 40);
$recentAssignments = array_slice($assignments, 0, 12);
?>
<style>
    .bgaming-campaign-grid { display:grid; grid-template-columns:1fr; gap:18px; align-items:start; }
    .bgaming-campaign-card { background: var(--bg-card); border:1px solid var(--border); border-radius:18px; box-shadow: var(--shadow-card); }
    .bgaming-campaign-head { padding:18px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .bgaming-campaign-head h2 { margin:0; font-size:18px; }
    .bgaming-campaign-body { padding:18px 20px; }
    .bgaming-inline-form { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .bgaming-inline-form .wide { grid-column:1 / -1; }
    .bgaming-meta { color: var(--t-muted); font-size:13px; }
    .bgaming-error { color: var(--danger, #e5484d); font-size:12px; margin:6px 0 0; }
    .bgaming-input-error { border-color: var(--danger, #e5484d) !important; }
    .bgaming-stack { display:flex; flex-direction:column; gap:18px; }
    .bgaming-summary { margin-top:14px; color:var(--t-muted); font-size:13px; line-height:1.45; }
    .bgaming-row-actions { display:flex; gap:6px; flex-wrap:wrap; }
    .bgaming-row-actions form { display:inline; }
    @media (max-width: 1080px) { .bgaming-inline-form { grid-template-columns:1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · BGaming</span>
        <h1 class="hero-title">BGaming <span class="accent">Freespin Oluştur</span></h1>
        <p class="hero-sub">Freespin kampanyası oluşturun ve seçilen oyunculara tanımlayın.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">BGaming Ayarları</a>
        <a class="btn btn--secondary" href="<?= $text(AdminAuth::url('/bgaming/freespins')) ?>">Freespin Listesi</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<?php if (!$bgamingActive): ?>
    <div class="alert alert--error" style="margin-bottom:16px">BGaming entegrasyonu pasif. Önce Ayarlar ekranından aktifleştirin.</div>
<?php elseif (!$freespinsEnabled): ?>
    <div class="alert alert--error" style="margin-bottom:16px">Freespin özelliği kapalı. Ayarlar ekranından açın.</div>
<?php endif; ?>

<div class="bgaming-campaign-grid">
    <div class="bgaming-stack">
        <section class="bgaming-campaign-card">
            <div class="bgaming-campaign-head">
                <h2><?= $isEdit ? 'Freespin Düzenle' : 'Yeni Freespin' ?></h2>
                <?php if ($isEdit): ?>
                    <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Yeni kayıt</a>
                <?php endif; ?>
            </div>
            <div class="bgaming-campaign-body">
                <form id="bgCreateForm" method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/store')) ?>">
                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                    <input type="hidden" name="campaign_type" value="freespin">
                    <input type="hidden" name="currency_code" value="<?= $text($value('currency_code', $defaultCurrency)) ?>">
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?= (int) ($editCampaign['id'] ?? 0) ?>">
                        <input type="hidden" name="active" value="<?= !empty($editCampaign['active']) ? '1' : '0' ?>">
                    <?php else: ?>
                        <input type="hidden" name="active" value="1">
                    <?php endif; ?>

                    <div class="bgaming-inline-form">
                        <div class="field">
                            <label class="field-label" for="campaign_title">Başlık</label>
                            <input id="campaign_title" class="input <?= $errorFor('title') !== '' ? 'bgaming-input-error' : '' ?>" type="text" name="title" required maxlength="190" value="<?= $text($value('title')) ?>" placeholder="Örn. Hoş geldin freespin">
                            <?php if ($errorFor('title') !== ''): ?><p class="bgaming-error"><?= $text($errorFor('title')) ?></p><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field-label" for="user_ids_text">Oyuncu ID</label>
                            <input id="user_ids_text" class="input <?= $errorFor('user_ids') !== '' ? 'bgaming-input-error' : '' ?>" type="text" name="user_ids" value="<?= $text($selectedUserIdsText) ?>" placeholder="örn. 12 veya 12, 45, 78" required>
                            <p class="bgaming-meta" style="margin-top:6px">Virgülle birden fazla oyuncu yazabilirsiniz.</p>
                            <?php if ($errorFor('user_ids') !== ''): ?><p class="bgaming-error"><?= $text($errorFor('user_ids')) ?></p><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field-label" for="game_identifier">Oyun</label>
                            <?php if ($freespinGames !== []): ?>
                                <select id="game_identifier" class="input <?= $errorFor('game_identifier') !== '' ? 'bgaming-input-error' : '' ?>" name="game_identifier" required>
                                    <option value="">Oyun seçin</option>
                                    <?php foreach ($freespinGames as $game): ?>
                                        <?php
                                        $identifier = (string) ($game['identifier'] ?? '');
                                        $gameTitle = (string) ($game['title'] ?? $identifier);
                                        ?>
                                        <option value="<?= $text($identifier) ?>" data-title="<?= $text($gameTitle) ?>"<?= $selectedGame === $identifier ? ' selected' : '' ?>><?= $text($gameTitle) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input id="game_identifier" class="input <?= $errorFor('game_identifier') !== '' ? 'bgaming-input-error' : '' ?>" type="text" name="game_identifier" required maxlength="120" value="<?= $text($selectedGame) ?>">
                                <p class="bgaming-meta" style="margin-top:6px">Oyun listesi boş. Ayarlar → Oyun Sync çalıştırın.</p>
                            <?php endif; ?>
                            <?php if ($errorFor('game_identifier') !== ''): ?><p class="bgaming-error"><?= $text($errorFor('game_identifier')) ?></p><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field-label" for="freespins_per_player">Kişi başı freespin</label>
                            <input id="freespins_per_player" class="input <?= $errorFor('freespins_per_player') !== '' ? 'bgaming-input-error' : '' ?>" type="number" min="1" max="1000" name="freespins_per_player" value="<?= $text($value('freespins_per_player', '10')) ?>" required>
                            <?php if ($errorFor('freespins_per_player') !== ''): ?><p class="bgaming-error"><?= $text($errorFor('freespins_per_player')) ?></p><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field-label" for="begins_at">Başlangıç (opsiyonel)</label>
                            <input id="begins_at" class="input admin-date-input <?= $errorFor('begins_at') !== '' ? 'bgaming-input-error' : '' ?>" type="datetime-local" name="begins_at" value="<?= $text($dateValue('begins_at')) ?>">
                            <?php if ($errorFor('begins_at') !== ''): ?><p class="bgaming-error"><?= $text($errorFor('begins_at')) ?></p><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field-label" for="expires_at">Bitiş</label>
                            <input id="expires_at" class="input admin-date-input <?= $errorFor('expires_at') !== '' ? 'bgaming-input-error' : '' ?>" type="datetime-local" name="expires_at" value="<?= $text($dateValue('expires_at', $defaultExpires)) ?>" required>
                            <?php if ($errorFor('expires_at') !== ''): ?><p class="bgaming-error"><?= $text($errorFor('expires_at')) ?></p><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field-label" for="bet_level">Bet seviyesi</label>
                            <input id="bet_level" class="input" type="number" min="0" step="1" name="bet_level" value="<?= $text($value('bet_level', '0')) ?>">
                            <p class="bgaming-meta" style="margin-top:6px">0 = varsayılan.</p>
                        </div>
                        <div class="field">
                            <label class="field-label" for="pick_user">Hızlı oyuncu seç (son 40)</label>
                            <select id="pick_user" class="input">
                                <option value="">Listeden ekle…</option>
                                <?php foreach ($recentUsers as $user): ?>
                                    <option value="<?= (int) ($user['id'] ?? 0) ?>">#<?= (int) ($user['id'] ?? 0) ?> · <?= $text($user['username'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <p class="bgaming-summary" id="bgCreateSummary"></p>
                    <div class="form-actions" style="margin-top:14px">
                        <button class="btn btn--primary" type="submit">Freespin Oluştur</button>
                    </div>
                </form>
            </div>
        </section>

        <?php if ($freespinCampaigns !== []): ?>
        <section class="bgaming-campaign-card">
            <div class="bgaming-campaign-head">
                <h2>Mevcut kayda oyuncu ekle</h2>
            </div>
            <div class="bgaming-campaign-body">
                <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assign')) ?>">
                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                    <div class="bgaming-inline-form">
                        <div class="field">
                            <label class="field-label" for="assign_campaign_id">Freespin kaydı</label>
                            <select id="assign_campaign_id" class="input" name="campaign_id" required>
                                <option value="">Seçin</option>
                                <?php foreach ($freespinCampaigns as $row): ?>
                                    <option value="<?= (int) ($row['id'] ?? 0) ?>">
                                        <?= $text(($row['title'] ?? '') . ' · ' . (int) ($row['freespins_per_player'] ?? 0) . ' spin') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="assign_user_ids">Oyuncu ID</label>
                            <input id="assign_user_ids" class="input" type="text" name="user_ids" required placeholder="örn. 12, 45">
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top:14px">
                        <button class="btn btn--secondary" type="submit">Oyuncuya Ekle</button>
                    </div>
                </form>
            </div>
        </section>
        <?php endif; ?>

        <section class="bgaming-campaign-card">
            <div class="bgaming-campaign-head">
                <h2>Son tanımlananlar</h2>
                <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/freespins')) ?>">Tüm liste</a>
            </div>
            <div class="admin-compact-table-wrap">
                <table class="admin-compact-table">
                    <thead>
                        <tr>
                            <th>Oyuncu</th>
                            <th>Başlık</th>
                            <th>Durum</th>
                            <th>Spin</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentAssignments === []): ?>
                            <tr><td colspan="5">Henüz kayıt yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentAssignments as $row): ?>
                                <?php
                                $status = strtolower((string) ($row['status'] ?? ''));
                                $assignmentId = (int) ($row['id'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <?= $text($row['username'] ?? ('#' . (int) ($row['user_id'] ?? 0))) ?>
                                        <div class="bgaming-meta">#<?= (int) ($row['user_id'] ?? 0) ?></div>
                                    </td>
                                    <td>
                                        <?= $text($row['title'] ?? 'Freespin') ?>
                                        <?php if (!empty($row['game_identifier'])): ?>
                                            <div class="bgaming-meta"><?= $text($row['game_identifier']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= $text($statusBadgeClass($status)) ?>"><?= $text($row['status_label'] ?? $status) ?></span></td>
                                    <td><?= (int) ($row['freespins_done'] ?? 0) ?> / <?= (int) ($row['freespins_total'] ?? $row['freespins_per_player'] ?? 0) ?></td>
                                    <td>
                                        <div class="bgaming-row-actions">
                                            <?php if (in_array($status, ['failed', 'pending'], true)): ?>
                                                <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assignments/retry')) ?>">
                                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                                    <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
                                                    <button class="btn btn--ghost" style="font-size:11px;padding:4px 8px" type="submit">Tekrar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($status === 'active'): ?>
                                                <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assignments/cancel')) ?>" onsubmit="return confirm('İptal edilsin mi?');">
                                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                                    <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
                                                    <button class="btn btn--ghost" style="font-size:11px;padding:4px 8px;color:var(--danger)" type="submit">İptal</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var idsInput = document.getElementById('user_ids_text');
    var pick = document.getElementById('pick_user');
    var summary = document.getElementById('bgCreateSummary');
    var gameSelect = document.getElementById('game_identifier');
    var titleInput = document.getElementById('campaign_title');

    function fieldValue(id) {
        var el = document.getElementById(id);
        return el ? (el.value || '').trim() : '';
    }

    function renderSummary() {
        if (!summary) return;
        var ids = fieldValue('user_ids_text') || 'oyuncu yok';
        var spins = fieldValue('freespins_per_player') || '0';
        var game = 'oyun seçilmedi';
        if (gameSelect && gameSelect.selectedIndex >= 0) {
            var opt = gameSelect.options[gameSelect.selectedIndex];
            if (opt && opt.value) game = opt.getAttribute('data-title') || opt.text;
        }
        var expires = fieldValue('expires_at');
        summary.textContent = 'Özet: ' + ids + ' · ' + spins + ' spin · ' + game
            + (expires ? (' · bitiş ' + expires.replace('T', ' ')) : '');
    }

    if (pick && idsInput) {
        pick.addEventListener('change', function () {
            if (!pick.value) return;
            var current = idsInput.value.trim();
            var parts = current === '' ? [] : current.split(/[\s,;]+/);
            if (parts.indexOf(pick.value) === -1) {
                parts.push(pick.value);
            }
            idsInput.value = parts.join(', ');
            pick.value = '';
            renderSummary();
        });
    }

    if (gameSelect && titleInput) {
        gameSelect.addEventListener('change', function () {
            if (titleInput.value.trim() !== '') return;
            var opt = gameSelect.options[gameSelect.selectedIndex];
            if (!opt || !opt.value) return;
            titleInput.value = (fieldValue('freespins_per_player') || '10') + ' freespin · ' + (opt.getAttribute('data-title') || opt.text);
        });
    }

    ['user_ids_text', 'freespins_per_player', 'game_identifier', 'expires_at'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', renderSummary);
        el.addEventListener('change', renderSummary);
    });
    renderSummary();
});
</script>
