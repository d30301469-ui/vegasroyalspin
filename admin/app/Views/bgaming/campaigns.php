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
$activeChecked = $hasOldInput
    ? !empty($oldInput['active'])
    : (!$isEdit || (int) ($editCampaign['active'] ?? 1) === 1);
$selectedUserIds = [];
foreach ((array) ($hasOldInput ? ($oldInput['user_ids'] ?? []) : []) as $selectedUserId) {
    $selectedUserIds[(int) $selectedUserId] = true;
}
$defaultCurrency = (string) ($configRow['currency'] ?? 'USD');
$freespinsEnabled = (int) ($configRow['freespins_enabled'] ?? 1) === 1;
$bgamingActive = (int) ($configRow['is_active'] ?? 0) === 1;

$freespinCampaigns = array_values(array_filter(
    $campaigns,
    static fn (array $row): bool => (string) ($row['campaign_type'] ?? '') === 'freespin'
        && (int) ($row['active'] ?? 0) === 1
));

$userLabel = static function (array $user): string {
    $label = '#' . (int) ($user['id'] ?? 0) . ' · ' . (string) ($user['username'] ?? '');
    if (!empty($user['email'])) {
        $label .= ' · ' . (string) $user['email'];
    }
    if ((int) ($user['banned'] ?? 0) === 1) {
        $label .= ' [BANLI]';
    }

    return $label;
};

$statusBadgeClass = static fn (string $status): string => match ($status) {
    'active' => 'success',
    'played', 'bonus_assigned' => 'info',
    'pending' => 'warning',
    'failed', 'canceled', 'expired' => 'danger',
    default => '',
};
?>
<style>
    .bg-give { max-width: 920px; }
    .bg-card { background: var(--bg-card); border:1px solid var(--border); border-radius:16px; box-shadow: var(--shadow-card); margin-bottom:16px; }
    .bg-card-head { padding:16px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap; }
    .bg-card-head h2 { margin:0; font-size:17px; }
    .bg-card-body { padding:18px; }
    .bg-howto { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin:0; padding:0; list-style:none; }
    .bg-howto li { background:var(--bg-soft, rgba(127,127,127,.06)); border:1px solid var(--border); border-radius:12px; padding:12px 14px; }
    .bg-howto strong { display:block; margin-bottom:4px; font-size:13px; }
    .bg-howto span { color:var(--t-muted); font-size:12px; line-height:1.45; }
    .bg-fields { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .bg-fields .wide { grid-column:1 / -1; }
    .bg-hint { color:var(--t-muted); font-size:12px; margin:6px 0 0; line-height:1.4; }
    .bg-error { color:var(--danger,#e5484d); font-size:12px; margin:6px 0 0; }
    .bg-bad { border-color:var(--danger,#e5484d) !important; }
    .bg-list { min-height:180px; padding:8px; }
    .bg-list option { padding:7px 8px; }
    .bg-summary { margin-top:14px; padding:12px 14px; border-radius:12px; background:var(--bg-soft, rgba(127,127,127,.06)); border:1px solid var(--border); font-size:14px; line-height:1.45; }
    .bg-table { width:100%; border-collapse:collapse; }
    .bg-table th, .bg-table td { padding:10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .bg-table th { color:var(--t-muted); font-size:12px; text-transform:uppercase; letter-spacing:.05em; }
    .bg-meta { color:var(--t-muted); font-size:12px; }
    .bg-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .bg-actions form { display:inline; }
    .bg-adv { margin-top:12px; }
    .bg-adv summary { cursor:pointer; color:var(--t-muted); font-size:13px; }
    .bg-adv-body { margin-top:12px; }
    @media (max-width:900px) { .bg-fields, .bg-howto { grid-template-columns:1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">BGaming</span>
        <h1 class="hero-title"><span class="accent">Freespin Ver</span></h1>
        <p class="hero-sub">Oyuncuya bedava spin vermek için: kullanıcıyı seç → oyunu seç → adedi ve bitiş tarihini yaz → Freespin Ver.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--secondary" href="<?= $text(AdminAuth::url('/bgaming/freespins')) ?>">Verilen Freespinler</a>
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">Ayarlar</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<?php if (!$bgamingActive): ?>
    <div class="alert alert--error" style="margin-bottom:16px">
        BGaming kapalı. Freespin verilemez. Önce <a href="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">Ayarlar</a> ekranından entegrasyonu açın.
    </div>
<?php elseif (!$freespinsEnabled): ?>
    <div class="alert alert--error" style="margin-bottom:16px">
        Freespin özelliği kapalı. <a href="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">Ayarlar</a> ekranından freespin seçeneğini açın.
    </div>
<?php endif; ?>

<section class="bg-card bg-give">
    <div class="bg-card-body">
        <ol class="bg-howto">
            <li><strong>1. Kim?</strong><span>Freespin alacak oyuncu(ları) seçin.</span></li>
            <li><strong>2. Ne?</strong><span>Oyunu, spin adedini ve son kullanma tarihini girin.</span></li>
            <li><strong>3. Ver</strong><span>“Freespin Ver”e basın. Oyuncu hesabında freespin görünür.</span></li>
        </ol>
    </div>
</section>

<section class="bg-card bg-give">
    <div class="bg-card-head">
        <h2><?= $isEdit ? 'Kaydı güncelle ve oyuncuya ver' : 'Yeni freespin ver' ?></h2>
        <?php if ($isEdit): ?>
            <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Sıfırla</a>
        <?php endif; ?>
    </div>
    <div class="bg-card-body">
        <form id="bgGiveForm" method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/store')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <input type="hidden" name="campaign_type" value="freespin">
            <input type="hidden" name="currency_code" value="<?= $text($value('currency_code', $defaultCurrency)) ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int) ($editCampaign['id'] ?? 0) ?>">
            <?php else: ?>
                <input type="hidden" name="active" value="1">
            <?php endif; ?>

            <div class="bg-fields">
                <div class="field wide">
                    <label class="field-label" for="store_user_search">Oyuncu</label>
                    <input id="store_user_search" class="input" type="text" placeholder="Ara: kullanıcı adı, ID veya e-posta" autocomplete="off">
                    <select id="store_user_ids" class="input bg-list <?= $errorFor('user_ids') !== '' ? 'bg-bad' : '' ?>" name="user_ids[]" multiple size="8" required>
                        <?php foreach ($users as $user): ?>
                            <?php $label = $userLabel($user); ?>
                            <option value="<?= (int) ($user['id'] ?? 0) ?>" data-search="<?= $text(strtolower($label)) ?>"<?= isset($selectedUserIds[(int) ($user['id'] ?? 0)]) ? ' selected' : '' ?>><?= $text($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="bg-hint">Birden fazla oyuncu seçmek için Ctrl (Windows) veya Cmd (Mac) kullanın.</p>
                    <?php if ($errorFor('user_ids') !== ''): ?><p class="bg-error"><?= $text($errorFor('user_ids')) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label" for="game_identifier">Oyun</label>
                    <?php if ($freespinGames !== []): ?>
                        <select id="game_identifier" class="input <?= $errorFor('game_identifier') !== '' ? 'bg-bad' : '' ?>" name="game_identifier" required>
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
                        <input id="game_identifier" class="input <?= $errorFor('game_identifier') !== '' ? 'bg-bad' : '' ?>" type="text" name="game_identifier" required maxlength="120" value="<?= $text($selectedGame) ?>" placeholder="Önce oyun listesini senkronize edin">
                        <p class="bg-hint">Oyun listesi boş. Ayarlar → Oyun Sync çalıştırın.</p>
                    <?php endif; ?>
                    <?php if ($errorFor('game_identifier') !== ''): ?><p class="bg-error"><?= $text($errorFor('game_identifier')) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label" for="freespins_per_player">Kaç spin?</label>
                    <input id="freespins_per_player" class="input <?= $errorFor('freespins_per_player') !== '' ? 'bg-bad' : '' ?>" type="number" min="1" max="1000" name="freespins_per_player" value="<?= $text($value('freespins_per_player', '10')) ?>" required>
                    <?php if ($errorFor('freespins_per_player') !== ''): ?><p class="bg-error"><?= $text($errorFor('freespins_per_player')) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label" for="expires_at">Son kullanma tarihi</label>
                    <input id="expires_at" class="input admin-date-input <?= $errorFor('expires_at') !== '' ? 'bg-bad' : '' ?>" type="datetime-local" name="expires_at" value="<?= $text($dateValue('expires_at', $defaultExpires)) ?>" required>
                    <p class="bg-hint">Bu tarihe kadar oynanmayan freespin geçersiz olur.</p>
                    <?php if ($errorFor('expires_at') !== ''): ?><p class="bg-error"><?= $text($errorFor('expires_at')) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label" for="campaign_title">Kayıt adı</label>
                    <input id="campaign_title" class="input <?= $errorFor('title') !== '' ? 'bg-bad' : '' ?>" type="text" name="title" maxlength="190" value="<?= $text($value('title')) ?>" placeholder="Örn. Hoş geldin 10 freespin" required>
                    <p class="bg-hint">Sadece panelde görünür; oyuncu bunu görmez.</p>
                    <?php if ($errorFor('title') !== ''): ?><p class="bg-error"><?= $text($errorFor('title')) ?></p><?php endif; ?>
                </div>
            </div>

            <details class="bg-adv">
                <summary>İleri ayarlar (isteğe bağlı)</summary>
                <div class="bg-adv-body bg-fields">
                    <div class="field">
                        <label class="field-label" for="begins_at">Başlangıç (boş = hemen)</label>
                        <input id="begins_at" class="input admin-date-input <?= $errorFor('begins_at') !== '' ? 'bg-bad' : '' ?>" type="datetime-local" name="begins_at" value="<?= $text($dateValue('begins_at')) ?>">
                        <?php if ($errorFor('begins_at') !== ''): ?><p class="bg-error"><?= $text($errorFor('begins_at')) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="bet_level">Bahis seviyesi</label>
                        <input id="bet_level" class="input" type="number" min="0" step="1" name="bet_level" value="<?= $text($value('bet_level', '0')) ?>">
                        <p class="bg-hint">0 = oyunun varsayılan ilk seviyesi.</p>
                    </div>
                    <div class="field wide">
                        <label class="field-label" for="campaign_notes">Not</label>
                        <textarea id="campaign_notes" class="input" name="notes" rows="2" style="resize:vertical"><?= $text($value('notes')) ?></textarea>
                    </div>
                    <?php if ($isEdit || $value('campaign_code') !== ''): ?>
                        <div class="field wide">
                            <label class="field-label" for="campaign_code">Sistem kodu</label>
                            <input id="campaign_code" class="input <?= $errorFor('campaign_code') !== '' ? 'bg-bad' : '' ?>" type="text" name="campaign_code" maxlength="190" value="<?= $text($value('campaign_code')) ?>" <?= $isEdit ? 'readonly' : '' ?>>
                            <?php if ($errorFor('campaign_code') !== ''): ?><p class="bg-error"><?= $text($errorFor('campaign_code')) ?></p><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($isEdit): ?>
                        <div class="field wide">
                            <label class="switch">
                                <input type="checkbox" name="active" value="1" <?= $activeChecked ? 'checked' : '' ?>>
                                <span class="track"></span>
                                Bu kayıt aktif kalsın
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
            </details>

            <div class="bg-summary" id="bgGiveSummary">Özet hazırlanıyor…</div>

            <div class="form-actions" style="margin-top:16px">
                <button class="btn btn--primary" type="submit">Freespin Ver</button>
            </div>
        </form>
    </div>
</section>

<?php if ($freespinCampaigns !== []): ?>
<section class="bg-card bg-give">
    <div class="bg-card-head">
        <h2>Aynı freespini başka oyuncuya da ver</h2>
    </div>
    <div class="bg-card-body">
        <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assign')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <div class="bg-fields">
                <div class="field">
                    <label class="field-label" for="assign_campaign_id">Önceki kayıt</label>
                    <select id="assign_campaign_id" class="input" name="campaign_id" required>
                        <option value="">Seçin</option>
                        <?php foreach ($freespinCampaigns as $row): ?>
                            <?php
                            $optionLabel = (string) ($row['title'] ?? '')
                                . ' · ' . (int) ($row['freespins_per_player'] ?? 0) . ' spin'
                                . ' · ' . (string) ($row['game_identifier'] ?? '-');
                            ?>
                            <option value="<?= (int) ($row['id'] ?? 0) ?>"><?= $text($optionLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="assign_user_search">Oyuncu ara</label>
                    <input id="assign_user_search" class="input" type="text" placeholder="Kullanıcı adı / ID" autocomplete="off">
                    <select id="assign_user_ids" class="input bg-list" name="user_ids[]" multiple size="6" required>
                        <?php foreach ($users as $user): ?>
                            <?php $label = $userLabel($user); ?>
                            <option value="<?= (int) ($user['id'] ?? 0) ?>" data-search="<?= $text(strtolower($label)) ?>"><?= $text($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions" style="margin-top:14px">
                <button class="btn btn--secondary" type="submit">Seçilenlere Ver</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="bg-card">
    <div class="bg-card-head">
        <h2>Son verilenler</h2>
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/freespins')) ?>">Tümünü gör</a>
    </div>
    <div class="bg-card-body" style="padding-top:4px">
        <table class="bg-table">
            <thead>
                <tr>
                    <th>Oyuncu</th>
                    <th>Ne verildi?</th>
                    <th>Durum</th>
                    <th>Spin</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($assignments === []): ?>
                    <tr><td colspan="5" class="bg-meta">Henüz freespin verilmedi.</td></tr>
                <?php else: ?>
                    <?php foreach (array_slice($assignments, 0, 15) as $row): ?>
                        <?php
                        $status = strtolower((string) ($row['status'] ?? ''));
                        $assignmentId = (int) ($row['id'] ?? 0);
                        $lastError = trim((string) ($row['last_error'] ?? ''));
                        $isFreespin = (string) ($row['campaign_type'] ?? '') === 'freespin';
                        $game = (string) ($row['game_identifier'] ?? '');
                        $title = (string) ($row['title'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <?= $text($row['username'] ?? ('#' . (int) ($row['user_id'] ?? 0))) ?>
                                <div class="bg-meta">#<?= (int) ($row['user_id'] ?? 0) ?></div>
                            </td>
                            <td>
                                <?= $text($title !== '' ? $title : 'Freespin') ?>
                                <?php if ($game !== ''): ?><div class="bg-meta"><?= $text($game) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge dot <?= $text($statusBadgeClass($status)) ?>"><?= $text($row['status_label'] ?? $status) ?></span>
                                <?php if ($lastError !== ''): ?><div class="bg-error"><?= $text($lastError) ?></div><?php endif; ?>
                            </td>
                            <td><?= (int) ($row['freespins_done'] ?? 0) ?> / <?= (int) ($row['freespins_total'] ?? $row['freespins_per_player'] ?? 0) ?></td>
                            <td>
                                <div class="bg-actions">
                                    <?php if ($isFreespin && in_array($status, ['failed', 'pending'], true)): ?>
                                        <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assignments/retry')) ?>">
                                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                            <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
                                            <button class="btn btn--secondary" type="submit">Tekrar Dene</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($isFreespin && $status === 'active'): ?>
                                        <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assignments/cancel')) ?>" onsubmit="return confirm('Bu freespin iptal edilsin mi?');">
                                            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                            <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
                                            <button class="btn btn--danger" type="submit">İptal</button>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('bgGiveForm');
    var summary = document.getElementById('bgGiveSummary');
    var userSelect = document.getElementById('store_user_ids');
    var gameSelect = document.getElementById('game_identifier');
    var titleInput = document.getElementById('campaign_title');

    function bindSearch(inputId, selectId) {
        var input = document.getElementById(inputId);
        var select = document.getElementById(selectId);
        if (!input || !select) return;
        var options = Array.prototype.slice.call(select.options);
        input.addEventListener('input', function () {
            var query = (input.value || '').toLowerCase().trim();
            options.forEach(function (option) {
                var haystack = (option.getAttribute('data-search') || option.text || '').toLowerCase();
                option.hidden = query !== '' && haystack.indexOf(query) === -1;
            });
        });
    }

    function selectedUsers() {
        if (!userSelect) return [];
        return Array.prototype.filter.call(userSelect.options, function (option) {
            return option.selected && !option.hidden;
        });
    }

    function fieldValue(id) {
        var el = document.getElementById(id);
        return el ? (el.value || '').trim() : '';
    }

    function gameLabel() {
        if (!gameSelect) return fieldValue('game_identifier') || 'oyun seçilmedi';
        var option = gameSelect.options[gameSelect.selectedIndex];
        if (!option || !option.value) return 'oyun seçilmedi';
        return option.getAttribute('data-title') || option.text || option.value;
    }

    function renderSummary() {
        if (!summary) return;
        var users = selectedUsers();
        var spins = fieldValue('freespins_per_player') || '0';
        var expires = fieldValue('expires_at');
        var who = users.length === 0
            ? 'oyuncu seçilmedi'
            : (users.length === 1 ? users[0].text : (users.length + ' oyuncu'));
        summary.textContent = who + ' → ' + spins + ' freespin · ' + gameLabel()
            + (expires ? (' · son tarih ' + expires.replace('T', ' ')) : '')
            + '.';
    }

    if (gameSelect && titleInput) {
        gameSelect.addEventListener('change', function () {
            if (titleInput.value.trim() !== '') return;
            var option = gameSelect.options[gameSelect.selectedIndex];
            if (!option || !option.value) return;
            var spins = fieldValue('freespins_per_player') || '10';
            titleInput.value = spins + ' freespin · ' + (option.getAttribute('data-title') || option.text);
            renderSummary();
        });
    }

    bindSearch('store_user_search', 'store_user_ids');
    bindSearch('assign_user_search', 'assign_user_ids');

    ['freespins_per_player', 'game_identifier', 'expires_at'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', renderSummary);
        el.addEventListener('input', renderSummary);
    });
    if (userSelect) userSelect.addEventListener('change', renderSummary);

    if (form) {
        form.addEventListener('submit', function (event) {
            if (selectedUsers().length === 0) {
                event.preventDefault();
                alert('En az bir oyuncu seçin.');
                return;
            }
            if (!window.confirm(summary.textContent + '\n\nOnaylıyor musunuz?')) {
                event.preventDefault();
            }
        });
    }

    renderSummary();
});
</script>
