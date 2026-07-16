<?php

declare(strict_types=1);

$configRow = is_array($configRow ?? null) ? $configRow : [];
$users = is_array($users ?? null) ? $users : [];
$localCampaigns = is_array($localCampaigns ?? null) ? $localCampaigns : [];
$remoteData = is_array($remoteData ?? null) ? $remoteData : ['data' => [], 'meta' => []];
$remoteItems = is_array($remoteData['data'] ?? null) ? $remoteData['data'] : [];
$remoteMeta = is_array($remoteData['meta'] ?? null) ? $remoteData['meta'] : [];
$remoteFilter = is_array($remoteFilter ?? null) ? $remoteFilter : [];
$flash = trim((string) ($flash ?? ''));
$remoteError = trim((string) ($remoteError ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<style>
    .bgaming-fs-grid { display:grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap:18px; align-items:start; }
    .bgaming-fs-card { background: var(--bg-card); border:1px solid var(--border); border-radius:18px; box-shadow: var(--shadow-card); }
    .bgaming-fs-head { padding:18px 20px; border-bottom:1px solid var(--border); }
    .bgaming-fs-body { padding:18px 20px; }
    .bgaming-fs-table { width:100%; border-collapse:collapse; }
    .bgaming-fs-table th, .bgaming-fs-table td { padding:11px 10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .bgaming-fs-table th { color: var(--t-muted); font-size:12px; text-transform:uppercase; letter-spacing:.06em; }
    .bgaming-fs-meta { color:var(--t-muted); font-size:13px; }
    .bgaming-fs-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; }
    .bgaming-fs-stack { display:flex; flex-direction:column; gap:16px; }
    .bgaming-fs-inline { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .bgaming-fs-guide { grid-column:1 / -1; background: var(--bg-card); border:1px solid var(--border); border-radius:18px; box-shadow: var(--shadow-card); padding:16px 20px; }
    .bgaming-fs-guide h2 { margin:0 0 10px; font-size:16px; }
    .bgaming-fs-steps { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin:0; padding:0; list-style:none; }
    .bgaming-fs-steps li { background: var(--bg-soft, rgba(127,127,127,.06)); border:1px solid var(--border); border-radius:12px; padding:12px 14px; }
    .bgaming-fs-steps b { display:block; font-size:13px; margin-bottom:4px; }
    .bgaming-fs-steps span { color: var(--t-muted); font-size:12px; line-height:1.4; }
    .bgaming-fs-step-badge { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:999px; background:var(--accent, #6c5ce7); color:#fff; font-size:12px; font-weight:600; margin-right:8px; }
    .bgaming-fs-hint { color: var(--t-muted); font-size:12px; margin:4px 0 0; }
    @media (max-width: 1080px) { .bgaming-fs-grid, .bgaming-fs-inline { grid-template-columns:1fr; } .bgaming-fs-steps { grid-template-columns:1fr 1fr; } }
    @media (max-width: 640px) { .bgaming-fs-steps { grid-template-columns:1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · BGaming</span>
        <h1 class="hero-title">BGaming <span class="accent">Freespin Yönetimi</span></h1>
        <p class="hero-sub">Issue, status sync, cancel ve remote liste işlemlerini panelden yönetin.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">BGaming Ayarları</a>
        <a class="btn btn--secondary" href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Kampanyalar</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info admin-alert-spaced"><?= $text($flash) ?></div>
<?php endif; ?>

<?php if ($remoteError !== ''): ?>
    <div class="alert alert--error admin-alert-spaced">Remote freespin API hatası: <?= $text($remoteError) ?></div>
<?php endif; ?>

<section class="bgaming-fs-guide">
    <h2>Freespin nasıl yönetilir?</h2>
    <ol class="bgaming-fs-steps">
        <li><b><span class="bgaming-fs-step-badge">1</span>Issue Gönder</b><span>Kullanıcıyı, oyunu ve spin adedini seçip BGaming'e freespin tanımlar. Issue ID boşsa otomatik üretilir.</span></li>
        <li><b><span class="bgaming-fs-step-badge">2</span>Status Sync</b><span>Issue ID ile BGaming'den güncel durumu (active / played / canceled / expired) çekip panele işler.</span></li>
        <li><b><span class="bgaming-fs-step-badge">3</span>Issue İptal</b><span>Henüz oynanmamış bir freespin'i iptal eder. Oynanan freespin iptal edilemez.</span></li>
        <li><b><span class="bgaming-fs-step-badge">4</span>Kazanç</b><span>Oyuncu spinleri bitirince BGaming <code>/freespins/finish</code> çağırır; kazanç bakiyeye tek sefer eklenir.</span></li>
    </ol>
</section>

<div class="bgaming-fs-grid">
    <div class="bgaming-fs-stack">
        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2 style="margin:0;font-size:18px">Freespin Issue</h2>
            </div>
            <div class="bgaming-fs-body">
                <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/issue')) ?>">
                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                    <div class="bgaming-fs-inline">
                        <div class="field">
                            <label class="field-label" for="fs_user_id">Kullanıcı</label>
                            <select id="fs_user_id" class="input" name="user_id" required>
                                <option value="">Kullanıcı seçin</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= (int) ($user['id'] ?? 0) ?>">#<?= (int) ($user['id'] ?? 0) ?> · <?= $text($user['username'] ?? '') ?><?php if (!empty($user['email'])): ?> (<?= $text($user['email']) ?>)<?php endif; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_issue_id">Issue ID (opsiyonel)</label>
                            <input id="fs_issue_id" class="input bgaming-fs-mono" type="text" name="issue_id" placeholder="Boşsa otomatik üretilir">
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_games">Game Identifier</label>
                            <input id="fs_games" class="input" type="text" name="games" required placeholder="acceptance:test veya CarnivalBonanza">
                            <p class="bgaming-fs-hint">Tek oyun kodu yazın. Birden fazla oyun için virgülle ayırın. Accept testinde <code>acceptance:test</code> kullanın.</p>
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_count">Freespin Adedi</label>
                            <input id="fs_count" class="input" type="number" name="freespins_quantity" min="1" max="1000" value="10" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_currency">Para Birimi</label>
                            <input id="fs_currency" class="input" type="text" name="currency" value="<?= $text($configRow['currency'] ?? 'USD') ?>" maxlength="8">
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_bet_level">Bet Level (opsiyonel)</label>
                            <input id="fs_bet_level" class="input" type="number" name="bet_level" min="0" step="1" value="0">
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_valid_since">Valid Since (opsiyonel)</label>
                            <input id="fs_valid_since" class="input admin-date-input" type="datetime-local" name="valid_since">
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_valid_until">Valid Until</label>
                            <input id="fs_valid_until" class="input admin-date-input" type="datetime-local" name="valid_until" required>
                            <p class="bgaming-fs-hint">Freespin son geçerlilik tarihi (zorunlu). Bu tarihe kadar oynanmayan freespin <code>expired</code> olur.</p>
                        </div>
                    </div>
                    <div class="form-actions admin-action-spaced">
                        <button class="btn btn--primary" type="submit">Issue Gönder</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2 style="margin:0;font-size:18px">Issue Yönetimi</h2>
            </div>
            <div class="bgaming-fs-body">
                <div class="bgaming-fs-inline">
                    <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/sync')) ?>">
                        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                        <div class="field">
                            <label class="field-label" for="sync_issue_id">Issue ID</label>
                            <input id="sync_issue_id" class="input bgaming-fs-mono" type="text" name="issue_id" required placeholder="fs_..."></input>
                        </div>
                        <button class="btn btn--secondary admin-full-action" type="submit">Status Sync</button>
                    </form>
                    <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/cancel')) ?>">
                        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                        <div class="field">
                            <label class="field-label" for="cancel_issue_id">Issue ID</label>
                            <input id="cancel_issue_id" class="input bgaming-fs-mono" type="text" name="issue_id" required placeholder="fs_..."></input>
                        </div>
                        <button class="btn btn--danger admin-full-action" type="submit">Issue İptal Et</button>
                    </form>
                </div>
                <p class="bgaming-fs-meta" style="margin-top:12px">Status sync işlemi BGaming Status endpointinden güncel durumu çekip local kampanya/atama tablosuna işler.</p>
            </div>
        </section>
    </div>

    <div class="bgaming-fs-stack">
        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2 style="margin:0;font-size:18px">Remote Freespin Listesi</h2>
            </div>
            <div class="bgaming-fs-body">
                <form method="get" action="<?= $text(AdminAuth::url('/bgaming/freespins')) ?>" class="bgaming-fs-inline" style="margin-bottom:14px">
                    <div class="field">
                        <label class="field-label" for="filter_user_id">User ID</label>
                        <input id="filter_user_id" class="input" type="number" name="user_id" min="0" value="<?= (int) ($remoteFilter['user_id'] ?? 0) ?>">
                    </div>
                    <div class="field">
                        <label class="field-label" for="filter_status">Status</label>
                        <select id="filter_status" class="input" name="status">
                            <?php $statusFilter = (string) ($remoteFilter['status'] ?? ''); ?>
                            <option value=""<?= $statusFilter === '' ? ' selected' : '' ?>>Hepsi</option>
                            <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>>active</option>
                            <option value="played"<?= $statusFilter === 'played' ? ' selected' : '' ?>>played</option>
                            <option value="canceled"<?= $statusFilter === 'canceled' ? ' selected' : '' ?>>canceled</option>
                            <option value="expired"<?= $statusFilter === 'expired' ? ' selected' : '' ?>>expired</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="filter_page">Page</label>
                        <input id="filter_page" class="input" type="number" name="page" min="1" value="<?= (int) ($remoteFilter['page'] ?? 1) ?>">
                    </div>
                    <div class="field" style="align-self:end">
                        <button class="btn btn--ghost admin-full-action" type="submit">Listele</button>
                    </div>
                </form>

                <table class="bgaming-fs-table">
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>Kullanıcı</th>
                            <th>Durum</th>
                            <th>Spin</th>
                            <th>Win</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($remoteItems === []): ?>
                            <tr><td colspan="5" class="bgaming-fs-meta">Remote freespin kaydı bulunamadı.</td></tr>
                        <?php else: ?>
                            <?php foreach ($remoteItems as $item): ?>
                                <tr>
                                    <td><span class="bgaming-fs-mono"><?= $text($item['issue_id'] ?? '') ?></span></td>
                                    <td>#<?= (int) ($item['user_id'] ?? 0) ?></td>
                                    <td><?= $text($item['status'] ?? '') ?></td>
                                    <td><?= (int) ($item['freespins_done'] ?? 0) ?> / <?= (int) ($item['freespins_quantity'] ?? $item['freespins_count'] ?? 0) ?></td>
                                    <td><?= (int) ($item['win_amount'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($remoteMeta !== []): ?>
                    <p class="bgaming-fs-meta" style="margin-top:10px">
                        Toplam: <?= (int) ($remoteMeta['total'] ?? 0) ?> ·
                        Sayfa: <?= (int) ($remoteMeta['page'] ?? 1) ?> / <?= (int) ($remoteMeta['last_page'] ?? 1) ?>
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2 style="margin:0;font-size:18px">Local Sync Durumu</h2>
            </div>
            <div class="bgaming-fs-body" style="padding-top:4px">
                <table class="bgaming-fs-table">
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>Durum</th>
                            <th>Aktif</th>
                            <th>Spin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($localCampaigns === []): ?>
                            <tr><td colspan="4" class="bgaming-fs-meta">Local freespin kampanyası bulunamadı.</td></tr>
                        <?php else: ?>
                            <?php foreach ($localCampaigns as $row): ?>
                                <tr>
                                    <td><span class="bgaming-fs-mono"><?= $text($row['campaign_code'] ?? '') ?></span></td>
                                    <td><?= $text($row['status'] ?? '') ?></td>
                                    <td><?= ((int) ($row['active'] ?? 0)) === 1 ? 'Evet' : 'Hayır' ?></td>
                                    <td><?= (int) ($row['freespins_per_player'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
