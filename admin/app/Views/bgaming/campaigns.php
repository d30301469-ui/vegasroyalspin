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

/** Form değeri: hatalı gönderim varsa kullanıcının girdiği değer, yoksa kampanya kaydı. */
$value = static function (string $field, mixed $default = '') use ($oldInput, $editCampaign, $hasOldInput): mixed {
    if ($hasOldInput && array_key_exists($field, $oldInput)) {
        return $oldInput[$field];
    }
    if (array_key_exists($field, $editCampaign) && $editCampaign[$field] !== null) {
        return $editCampaign[$field];
    }

    return $default;
};

$dateValue = static function (string $field) use ($value, $dateTimeLocal): string {
    return $dateTimeLocal($value($field, ''));
};

$errorFor = static fn (string $field): string => (string) ($errors[$field] ?? '');
$campaignType = (string) $value('campaign_type', 'freespin');
$campaignType = in_array($campaignType, ['freespin', 'promo'], true) ? $campaignType : 'freespin';
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

$userLabel = static function (array $user): string {
    $label = '#' . (int) ($user['id'] ?? 0) . ' · ' . (string) ($user['username'] ?? '');
    $fullName = trim((string) ($user['name'] ?? '') . ' ' . (string) ($user['surname'] ?? ''));
    if ($fullName !== '') {
        $label .= ' (' . $fullName . ')';
    }
    if (!empty($user['email'])) {
        $label .= ' - ' . (string) $user['email'];
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
    .bg-fs-card { background: var(--bg-card); border:1px solid var(--border); border-radius:18px; box-shadow: var(--shadow-card); margin-bottom:18px; }
    .bg-fs-card-head { padding:18px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .bg-fs-card-head h2 { margin:0; font-size:18px; }
    .bg-fs-card-body { padding:18px 20px; }
    .bg-fs-steps { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin:0; padding:0; list-style:none; }
    .bg-fs-steps li { background: var(--bg-soft, rgba(127,127,127,.06)); border:1px solid var(--border); border-radius:12px; padding:12px 14px; }
    .bg-fs-steps b { display:block; font-size:13px; margin-bottom:4px; }
    .bg-fs-steps span { color: var(--t-muted); font-size:12px; line-height:1.45; }
    .bg-fs-step-badge { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:999px; background:var(--accent, #6c5ce7); color:#fff; font-size:12px; font-weight:600; margin-right:8px; }
    .bg-fs-section { border:1px solid var(--border); border-radius:14px; padding:16px; margin-bottom:16px; }
    .bg-fs-section > h3 { margin:0 0 4px; font-size:14px; text-transform:uppercase; letter-spacing:.06em; color:var(--t-muted); }
    .bg-fs-section > p.bg-fs-hint { margin:0 0 14px; }
    .bg-fs-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .bg-fs-grid .bg-fs-wide { grid-column:1 / -1; }
    .bg-fs-hint { color:var(--t-muted); font-size:12px; line-height:1.45; margin:6px 0 0; }
    .bg-fs-error { color: var(--danger, #e5484d); font-size:12px; margin:6px 0 0; }
    .bg-fs-input-error { border-color: var(--danger, #e5484d) !important; }
    .bg-fs-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; }
    .bg-fs-listbox { min-height:190px; padding:8px; }
    .bg-fs-listbox option { padding:7px 9px; border-radius:8px; }
    .bg-fs-table { width:100%; border-collapse:collapse; }
    .bg-fs-table th, .bg-fs-table td { padding:11px 10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .bg-fs-table th { color:var(--t-muted); font-size:12px; text-transform:uppercase; letter-spacing:.06em; }
    .bg-fs-meta { color:var(--t-muted); font-size:13px; }
    .bg-fs-summary { border:1px dashed var(--border); border-radius:12px; padding:12px 14px; color:var(--t-muted); font-size:13px; line-height:1.5; }
    .bg-fs-row-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .bg-fs-row-actions form { display:inline; }
    @media (max-width: 1080px) { .bg-fs-grid { grid-template-columns:1fr; } .bg-fs-steps { grid-template-columns:1fr 1fr; } }
    @media (max-width: 640px) { .bg-fs-steps { grid-template-columns:1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · BGaming</span>
        <h1 class="hero-title">BGaming <span class="accent">Freespin Kampanyaları</span></h1>
        <p class="hero-sub">Kampanyayı oluşturun ve aynı ekranda kullanıcılara ekleyin. Freespin, kaydettiğiniz anda BGaming tarafında tanımlanır.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">BGaming Ayarları</a>
        <a class="btn btn--secondary" href="<?= $text(AdminAuth::url('/bgaming/freespins')) ?>">Freespin Takibi</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<?php if (!$bgamingActive): ?>
    <div class="alert alert--error" style="margin-bottom:16px">
        BGaming entegrasyonu pasif. Kampanya kaydedilir ama kullanıcıya freespin eklenemez. BGaming Ayarları ekranından entegrasyonu aktifleştirin.
    </div>
<?php elseif (!$freespinsEnabled): ?>
    <div class="alert alert--error" style="margin-bottom:16px">
        BGaming ayarlarında freespin özelliği kapalı. Kullanıcıya freespin eklemek için önce bu seçeneği açın.
    </div>
<?php endif; ?>

<section class="bg-fs-card">
    <div class="bg-fs-card-body">
        <ol class="bg-fs-steps">
            <li><b><span class="bg-fs-step-badge">1</span>Kampanya bilgisi</b><span>Başlık ve tip (freespin / promo) girin. Kampanya kodu boş bırakılırsa otomatik üretilir.</span></li>
            <li><b><span class="bg-fs-step-badge">2</span>Freespin ayarı</b><span>Freespin destekli oyun, kişi başı spin adedi, para birimi ve zorunlu bitiş tarihini girin.</span></li>
            <li><b><span class="bg-fs-step-badge">3</span>Kullanıcı seçimi</b><span>Freespini alacak kullanıcıları seçin. Boş bırakırsanız kampanya sadece şablon olarak kaydedilir.</span></li>
            <li><b><span class="bg-fs-step-badge">4</span>Kaydet ve ekle</b><span>Kaydettiğinizde BGaming'e issue gönderilir; sonuç aşağıdaki atama tablosunda görünür.</span></li>
        </ol>
    </div>
</section>

<section class="bg-fs-card">
    <div class="bg-fs-card-head">
        <h2><?= $isEdit ? 'Kampanyayı Düzenle ve Kullanıcı Ekle' : 'Kampanya Oluştur ve Kullanıcıya Ekle' ?></h2>
        <?php if ($isEdit): ?>
            <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Yeni kampanya formu</a>
        <?php endif; ?>
    </div>
    <div class="bg-fs-card-body">
        <form id="bgCampaignForm" method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/store')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) ($editCampaign['id'] ?? 0) ?>"><?php endif; ?>

            <div class="bg-fs-section">
                <h3>1 · Kampanya bilgisi</h3>
                <div class="bg-fs-grid">
                    <div class="field">
                        <label class="field-label" for="campaign_title">Kampanya adı</label>
                        <input id="campaign_title" class="input <?= $errorFor('title') !== '' ? 'bg-fs-input-error' : '' ?>" type="text" name="title" required maxlength="190" value="<?= $text($value('title')) ?>" placeholder="Örn. Hoş geldin freespin">
                        <?php if ($errorFor('title') !== ''): ?><p class="bg-fs-error"><?= $text($errorFor('title')) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="campaign_type">Kampanya tipi</label>
                        <select id="campaign_type" class="input" name="campaign_type">
                            <option value="freespin"<?= $campaignType === 'freespin' ? ' selected' : '' ?>>Freespin (BGaming oyununda bedava spin)</option>
                            <option value="promo"<?= $campaignType === 'promo' ? ' selected' : '' ?>>Promo (bonus bakiyesi)</option>
                        </select>
                        <p class="bg-fs-hint">Freespin sağlayıcıya gönderilir, promo yalnızca site içi bonus bakiyesi tanımlar.</p>
                    </div>
                    <div class="field">
                        <label class="field-label" for="begins_at">Başlangıç tarihi (opsiyonel)</label>
                        <input id="begins_at" class="input admin-date-input <?= $errorFor('begins_at') !== '' ? 'bg-fs-input-error' : '' ?>" type="datetime-local" name="begins_at" value="<?= $text($dateValue('begins_at')) ?>">
                        <p class="bg-fs-hint">Boş bırakılırsa kampanya hemen başlar.</p>
                        <?php if ($errorFor('begins_at') !== ''): ?><p class="bg-fs-error"><?= $text($errorFor('begins_at')) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="expires_at">Bitiş tarihi</label>
                        <input id="expires_at" class="input admin-date-input <?= $errorFor('expires_at') !== '' ? 'bg-fs-input-error' : '' ?>" type="datetime-local" name="expires_at" value="<?= $text($dateValue('expires_at')) ?>">
                        <p class="bg-fs-hint">Freespin için zorunlu: sağlayıcıdaki geçerlilik süresi bu tarihten alınır. Promo için bonus çevrim son tarihi olur.</p>
                        <?php if ($errorFor('expires_at') !== ''): ?><p class="bg-fs-error"><?= $text($errorFor('expires_at')) ?></p><?php endif; ?>
                    </div>
                    <div class="field bg-fs-wide">
                        <label class="switch" style="margin-top:4px">
                            <input type="checkbox" name="active" value="1" <?= $activeChecked ? 'checked' : '' ?>>
                            <span class="track"></span>
                            Kampanya aktif (pasif kampanya kullanıcıya eklenemez)
                        </label>
                    </div>
                </div>
            </div>

            <div class="bg-fs-section" data-bg-section="freespin">
                <h3>2 · Freespin ayarı</h3>
                <p class="bg-fs-hint">Bu alanlar doğrudan BGaming <code>/promo/freespins</code> isteğine gider.</p>
                <div class="bg-fs-grid">
                    <div class="field">
                        <label class="field-label" for="game_identifier">Oyun</label>
                        <?php if ($freespinGames !== []): ?>
                            <select id="game_identifier" class="input <?= $errorFor('game_identifier') !== '' ? 'bg-fs-input-error' : '' ?>" name="game_identifier">
                                <option value="">Freespin destekli oyun seçin</option>
                                <option value="acceptance:test"<?= $selectedGame === 'acceptance:test' ? ' selected' : '' ?>>acceptance:test (API testi)</option>
                                <?php foreach ($freespinGames as $game): ?>
                                    <?php
                                    $identifier = (string) ($game['identifier'] ?? '');
                                    $gameTitle = (string) ($game['title'] ?? $identifier);
                                    ?>
                                    <option value="<?= $text($identifier) ?>"<?= $selectedGame === $identifier ? ' selected' : '' ?>><?= $text($gameTitle) ?> (<?= $text($identifier) ?>)</option>
                                <?php endforeach; ?>
                                <?php if ($selectedGame !== '' && $selectedGame !== 'acceptance:test' && !in_array($selectedGame, array_column($freespinGames, 'identifier'), true)): ?>
                                    <option value="<?= $text($selectedGame) ?>" selected><?= $text($selectedGame) ?> (katalogda freespin desteği yok)</option>
                                <?php endif; ?>
                            </select>
                            <p class="bg-fs-hint">Sadece <code>api_freespins</code> destekli oyunlar listelenir. Liste eksikse Ayarlar ekranından oyun senkronizasyonu çalıştırın.</p>
                        <?php else: ?>
                            <input id="game_identifier" class="input <?= $errorFor('game_identifier') !== '' ? 'bg-fs-input-error' : '' ?>" type="text" name="game_identifier" maxlength="120" value="<?= $text($selectedGame) ?>" placeholder="acceptance:test">
                            <p class="bg-fs-hint">Freespin destekli oyun bulunamadı. Önce BGaming oyun senkronizasyonu çalıştırın; aksi halde sağlayıcı isteği reddeder.</p>
                        <?php endif; ?>
                        <?php if ($errorFor('game_identifier') !== ''): ?><p class="bg-fs-error"><?= $text($errorFor('game_identifier')) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="freespins_per_player">Kişi başı freespin adedi</label>
                        <input id="freespins_per_player" class="input <?= $errorFor('freespins_per_player') !== '' ? 'bg-fs-input-error' : '' ?>" type="number" min="1" max="1000" name="freespins_per_player" value="<?= $text($value('freespins_per_player', '10')) ?>">
                        <?php if ($errorFor('freespins_per_player') !== ''): ?><p class="bg-fs-error"><?= $text($errorFor('freespins_per_player')) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="currency_code">Para birimi</label>
                        <input id="currency_code" class="input" type="text" name="currency_code" maxlength="8" value="<?= $text($value('currency_code', $defaultCurrency)) ?>">
                        <p class="bg-fs-hint">Desteklenmeyen değer girilirse BGaming ayarlarındaki para birimi kullanılır.</p>
                    </div>
                    <div class="field">
                        <label class="field-label" for="bet_level">Bet seviyesi</label>
                        <input id="bet_level" class="input" type="number" min="0" step="1" name="bet_level" value="<?= $text($value('bet_level', '0')) ?>">
                        <p class="bg-fs-hint">0 bırakılırsa oyunun ilk bet seviyesi (1) kullanılır.</p>
                    </div>
                </div>
            </div>

            <div class="bg-fs-section" data-bg-section="promo">
                <h3>2 · Promo ayarı</h3>
                <p class="bg-fs-hint">Promo kampanyası sağlayıcıya gitmez; kullanıcıya site içi bonus bakiyesi tanımlar.</p>
                <div class="bg-fs-grid">
                    <div class="field">
                        <label class="field-label" for="promo_amount">Promo tutarı</label>
                        <input id="promo_amount" class="input <?= $errorFor('promo_amount') !== '' ? 'bg-fs-input-error' : '' ?>" type="number" min="0" step="0.01" name="promo_amount" value="<?= $text($value('promo_amount', '0.00')) ?>">
                        <?php if ($errorFor('promo_amount') !== ''): ?><p class="bg-fs-error"><?= $text($errorFor('promo_amount')) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="wagering_multiplier">Çevrim çarpanı</label>
                        <input id="wagering_multiplier" class="input" type="number" min="0" step="0.10" name="wagering_multiplier" value="<?= $text($value('wagering_multiplier', '0')) ?>">
                    </div>
                </div>
            </div>

            <div class="bg-fs-section">
                <h3>3 · Kullanıcı seçimi</h3>
                <p class="bg-fs-hint">Birden fazla kullanıcı seçebilirsiniz (Ctrl / Shift ile). Boş bırakırsanız kampanya sadece şablon olarak kaydedilir.</p>
                <div class="field">
                    <label class="field-label" for="store_user_search">Kullanıcı ara</label>
                    <input id="store_user_search" class="input" type="text" placeholder="ID, kullanıcı adı veya e-posta">
                    <select id="store_user_ids" class="input bg-fs-listbox <?= $errorFor('user_ids') !== '' ? 'bg-fs-input-error' : '' ?>" name="user_ids[]" multiple size="10">
                        <?php foreach ($users as $user): ?>
                            <?php $label = $userLabel($user); ?>
                            <option value="<?= (int) ($user['id'] ?? 0) ?>" data-search="<?= $text(strtolower($label)) ?>"<?= isset($selectedUserIds[(int) ($user['id'] ?? 0)]) ? ' selected' : '' ?>><?= $text($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorFor('user_ids') !== ''): ?><p class="bg-fs-error"><?= $text($errorFor('user_ids')) ?></p><?php endif; ?>
                </div>
            </div>

            <div class="bg-fs-section">
                <h3>Gelişmiş</h3>
                <div class="bg-fs-grid">
                    <div class="field">
                        <label class="field-label" for="campaign_code">Kampanya kodu</label>
                        <input id="campaign_code" class="input bg-fs-mono <?= $errorFor('campaign_code') !== '' ? 'bg-fs-input-error' : '' ?>" type="text" name="campaign_code" maxlength="190" value="<?= $text($value('campaign_code')) ?>" placeholder="Boş bırakın, otomatik üretilir">
                        <p class="bg-fs-hint">Teknik kimlik. Sağlayıcıya gönderilen issue kimliği bu koddan ve kullanıcı numarasından türetilir.</p>
                        <?php if ($errorFor('campaign_code') !== ''): ?><p class="bg-fs-error"><?= $text($errorFor('campaign_code')) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="campaign_notes">Not</label>
                        <textarea id="campaign_notes" class="input" name="notes" rows="3" style="resize:vertical"><?= $text($value('notes')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-fs-summary" id="bgCampaignSummary"></div>

            <div class="form-actions" style="margin-top:16px">
                <button class="btn btn--primary" type="submit">
                    <?= $isEdit ? 'Kampanyayı Güncelle ve Seçilenlere Ekle' : 'Kampanyayı Oluştur ve Kullanıcıya Ekle' ?>
                </button>
            </div>
        </form>
    </div>
</section>

<section class="bg-fs-card">
    <div class="bg-fs-card-head">
        <h2>Mevcut kampanyaya kullanıcı ekle</h2>
    </div>
    <div class="bg-fs-card-body">
        <?php if ($campaigns === []): ?>
            <p class="bg-fs-meta">Henüz kampanya yok. Yukarıdaki formdan ilk kampanyayı oluşturun.</p>
        <?php else: ?>
            <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assign')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <div class="bg-fs-grid">
                    <div class="field">
                        <label class="field-label" for="assign_campaign_id">Kampanya</label>
                        <select id="assign_campaign_id" class="input" name="campaign_id" required>
                            <option value="">Kampanya seçin</option>
                            <?php foreach ($campaigns as $row): ?>
                                <?php
                                $rowType = (string) ($row['campaign_type'] ?? 'freespin');
                                $detail = $rowType === 'promo'
                                    ? number_format((float) ($row['promo_amount'] ?? 0), 2, ',', '.') . ' promo'
                                    : (int) ($row['freespins_per_player'] ?? 0) . ' spin · ' . (string) ($row['game_identifier'] ?? '-');
                                $optionLabel = (string) ($row['title'] ?? '') . ' — ' . strtoupper($rowType) . ' · ' . $detail
                                    . ((int) ($row['active'] ?? 0) === 1 ? '' : ' · PASİF');
                                ?>
                                <option value="<?= (int) ($row['id'] ?? 0) ?>"<?= (int) ($row['active'] ?? 0) === 1 ? '' : ' disabled' ?>><?= $text($optionLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="assign_user_search">Kullanıcı ara</label>
                        <input id="assign_user_search" class="input" type="text" placeholder="ID, kullanıcı adı veya e-posta">
                        <select id="assign_user_ids" class="input bg-fs-listbox" name="user_ids[]" multiple size="8" required>
                            <?php foreach ($users as $user): ?>
                                <?php $label = $userLabel($user); ?>
                                <option value="<?= (int) ($user['id'] ?? 0) ?>" data-search="<?= $text(strtolower($label)) ?>"><?= $text($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:14px">
                    <button class="btn btn--secondary" type="submit">Seçilen Kullanıcılara Ekle</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="bg-fs-card">
    <div class="bg-fs-card-head">
        <h2>Kampanyalar</h2>
    </div>
    <div class="bg-fs-card-body" style="padding-top:4px">
        <table class="bg-fs-table">
            <thead>
                <tr>
                    <th>Kampanya</th>
                    <th>Tip</th>
                    <th>Değer</th>
                    <th>Geçerlilik</th>
                    <th>Durum</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($campaigns === []): ?>
                    <tr><td colspan="6" class="bg-fs-meta">Henüz BGaming kampanyası tanımlanmadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($campaigns as $row): ?>
                        <?php
                        $rowType = (string) ($row['campaign_type'] ?? 'freespin');
                        $rowBegins = (int) ($row['begins_at'] ?? 0);
                        $rowExpires = (int) ($row['expires_at'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <strong><?= $text($row['title'] ?? '') ?></strong><br>
                                <span class="bg-fs-mono"><?= $text($row['campaign_code'] ?? '') ?></span>
                                <?php if (!empty($row['game_identifier'])): ?>
                                    <div class="bg-fs-meta">Oyun: <?= $text($row['game_identifier']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= $rowType === 'promo' ? 'Promo' : 'Freespin' ?></td>
                            <td>
                                <?php if ($rowType === 'promo'): ?>
                                    <?= $text(number_format((float) ($row['promo_amount'] ?? 0), 2, ',', '.')) ?>
                                <?php else: ?>
                                    <?= (int) ($row['freespins_per_player'] ?? 0) ?> spin
                                <?php endif; ?>
                            </td>
                            <td class="bg-fs-meta">
                                <?= $rowBegins > 0 ? $text(date('d.m.Y H:i', $rowBegins)) : 'Hemen' ?><br>
                                <?= $rowExpires > 0 ? $text(date('d.m.Y H:i', $rowExpires)) : 'Bitiş yok' ?>
                            </td>
                            <td>
                                <span class="badge dot <?= (int) ($row['active'] ?? 0) === 1 ? 'success' : 'danger' ?>">
                                    <?= (int) ($row['active'] ?? 0) === 1 ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </td>
                            <td><a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/campaigns?id=' . (int) ($row['id'] ?? 0))) ?>">Düzenle</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="bg-fs-card">
    <div class="bg-fs-card-head">
        <h2>Kullanıcı atamaları</h2>
        <span class="bg-fs-meta">Durum sağlayıcı yanıtına ve <code>/freespins/finish</code> callback'ine göre güncellenir.</span>
    </div>
    <div class="bg-fs-card-body" style="padding-top:4px">
        <table class="bg-fs-table">
            <thead>
                <tr>
                    <th>Kullanıcı</th>
                    <th>Kampanya</th>
                    <th>Durum</th>
                    <th>Spin</th>
                    <th>Issue kimliği</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($assignments === []): ?>
                    <tr><td colspan="6" class="bg-fs-meta">Henüz kullanıcıya kampanya eklenmedi.</td></tr>
                <?php else: ?>
                    <?php foreach ($assignments as $row): ?>
                        <?php
                        $status = strtolower((string) ($row['status'] ?? ''));
                        $assignmentId = (int) ($row['id'] ?? 0);
                        $lastError = trim((string) ($row['last_error'] ?? ''));
                        $isFreespin = (string) ($row['campaign_type'] ?? '') === 'freespin';
                        ?>
                        <tr>
                            <td>
                                #<?= (int) ($row['user_id'] ?? 0) ?>
                                <?php if (!empty($row['username'])): ?><div class="bg-fs-meta"><?= $text($row['username']) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <?= $text($row['title'] ?? '') ?>
                                <div class="bg-fs-meta bg-fs-mono"><?= $text($row['campaign_code'] ?? '') ?></div>
                            </td>
                            <td>
                                <span class="badge dot <?= $text($statusBadgeClass($status)) ?>"><?= $text($row['status_label'] ?? $status) ?></span>
                                <?php if ($lastError !== ''): ?>
                                    <div class="bg-fs-error"><?= $text($lastError) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) ($row['freespins_done'] ?? 0) ?> / <?= (int) ($row['freespins_total'] ?? $row['freespins_per_player'] ?? 0) ?></td>
                            <td><span class="bg-fs-mono"><?= $text($row['remote_issue_id'] ?? '-') ?></span></td>
                            <td>
                                <div class="bg-fs-row-actions">
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
                                            <button class="btn btn--danger" type="submit">İptal Et</button>
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
    var form = document.getElementById('bgCampaignForm');
    var typeSelect = document.getElementById('campaign_type');
    var summary = document.getElementById('bgCampaignSummary');
    var userSelect = document.getElementById('store_user_ids');

    function bindSearch(inputId, selectId) {
        var input = document.getElementById(inputId);
        var select = document.getElementById(selectId);
        if (!input || !select) {
            return;
        }
        var options = Array.prototype.slice.call(select.options);
        input.addEventListener('input', function () {
            var query = (input.value || '').toLowerCase().trim();
            options.forEach(function (option) {
                var haystack = (option.getAttribute('data-search') || option.text || '').toLowerCase();
                option.hidden = query !== '' && haystack.indexOf(query) === -1;
            });
        });
    }

    function selectedUserCount() {
        if (!userSelect) {
            return 0;
        }
        return Array.prototype.filter.call(userSelect.options, function (option) {
            return option.selected;
        }).length;
    }

    function applyType() {
        if (!typeSelect) {
            return;
        }
        var type = typeSelect.value === 'promo' ? 'promo' : 'freespin';
        document.querySelectorAll('[data-bg-section]').forEach(function (section) {
            var isMatch = section.getAttribute('data-bg-section') === type;
            section.style.display = isMatch ? '' : 'none';
            section.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !isMatch;
            });
        });
        renderSummary();
    }

    function fieldValue(id) {
        var element = document.getElementById(id);
        return element ? (element.value || '').trim() : '';
    }

    function renderSummary() {
        if (!summary || !typeSelect) {
            return;
        }
        var users = selectedUserCount();
        var target = users > 0 ? (users + ' kullanıcıya eklenecek') : 'Kullanıcı seçilmedi, sadece şablon kaydedilecek';

        if (typeSelect.value === 'promo') {
            var amount = fieldValue('promo_amount') || '0';
            summary.textContent = 'Özet: ' + amount + ' promo bonusu · ' + target + '.';
            return;
        }

        var spins = fieldValue('freespins_per_player') || '0';
        var game = fieldValue('game_identifier') || 'oyun seçilmedi';
        var expires = fieldValue('expires_at');
        summary.textContent = 'Özet: ' + spins + ' freespin · ' + game + ' · '
            + (expires !== '' ? ('bitiş ' + expires.replace('T', ' ')) : 'bitiş tarihi girilmedi')
            + ' · ' + target + '.';
    }

    bindSearch('store_user_search', 'store_user_ids');
    bindSearch('assign_user_search', 'assign_user_ids');

    if (typeSelect) {
        typeSelect.addEventListener('change', applyType);
    }
    ['freespins_per_player', 'game_identifier', 'expires_at', 'promo_amount'].forEach(function (id) {
        var element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', renderSummary);
            element.addEventListener('input', renderSummary);
        }
    });
    if (userSelect) {
        userSelect.addEventListener('change', renderSummary);
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            if (selectedUserCount() === 0) {
                return;
            }
            if (!window.confirm(summary ? summary.textContent + '\nOnaylıyor musunuz?' : 'Kampanya kaydedilsin mi?')) {
                event.preventDefault();
            }
        });
    }

    applyType();
});
</script>
