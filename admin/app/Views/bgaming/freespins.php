<?php

declare(strict_types=1);

$configRow = is_array($configRow ?? null) ? $configRow : [];
$users = is_array($users ?? null) ? $users : [];
$freespinGames = is_array($freespinGames ?? null) ? $freespinGames : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$remoteData = is_array($remoteData ?? null) ? $remoteData : ['data' => [], 'meta' => []];
$remoteItems = is_array($remoteData['data'] ?? null) ? $remoteData['data'] : [];
$remoteMeta = is_array($remoteData['meta'] ?? null) ? $remoteData['meta'] : [];
$remoteFilter = is_array($remoteFilter ?? null) ? $remoteFilter : [];
$flash = trim((string) ($flash ?? ''));
$remoteError = trim((string) ($remoteError ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$statusBadgeClass = static fn (string $status): string => match ($status) {
    'active' => 'success',
    'played', 'bonus_assigned' => 'info',
    'pending' => 'warning',
    'failed', 'canceled', 'expired' => 'danger',
    default => '',
};
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
    .bgaming-fs-error { color: var(--danger, #e5484d); font-size:12px; }
    .bgaming-fs-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; }
    .bgaming-fs-stack { display:flex; flex-direction:column; gap:16px; }
    .bgaming-fs-inline { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .bgaming-fs-guide { grid-column:1 / -1; background: var(--bg-card); border:1px solid var(--border); border-radius:18px; box-shadow: var(--shadow-card); padding:16px 20px; margin-bottom:18px; }
    .bgaming-fs-guide h2 { margin:0 0 10px; font-size:16px; }
    .bgaming-fs-steps { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin:0; padding:0; list-style:none; }
    .bgaming-fs-steps li { background: var(--bg-soft, rgba(127,127,127,.06)); border:1px solid var(--border); border-radius:12px; padding:12px 14px; }
    .bgaming-fs-steps b { display:block; font-size:13px; margin-bottom:4px; }
    .bgaming-fs-steps span { color: var(--t-muted); font-size:12px; line-height:1.4; }
    .bgaming-fs-step-badge { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:999px; background:var(--accent, #6c5ce7); color:#fff; font-size:12px; font-weight:600; margin-right:8px; }
    .bgaming-fs-hint { color: var(--t-muted); font-size:12px; margin:4px 0 0; }
    .bgaming-fs-advanced summary { cursor:pointer; font-weight:600; }
    .bgaming-fs-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .bgaming-fs-actions form { display:inline; }
    @media (max-width: 1080px) { .bgaming-fs-grid, .bgaming-fs-inline { grid-template-columns:1fr; } .bgaming-fs-steps { grid-template-columns:1fr 1fr; } }
    @media (max-width: 640px) { .bgaming-fs-steps { grid-template-columns:1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · BGaming</span>
        <h1 class="hero-title">BGaming <span class="accent">Freespin Takibi</span></h1>
        <p class="hero-sub">Verilen freespinlerin durumunu izleyin, sağlayıcıyla senkronize edin veya iptal edin.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">BGaming Ayarları</a>
        <a class="btn btn--primary" href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Kampanya Oluştur ve Ekle</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info admin-alert-spaced"><?= $text($flash) ?></div>
<?php endif; ?>

<?php if ($remoteError !== ''): ?>
    <div class="alert alert--error admin-alert-spaced">Remote freespin API hatası: <?= $text($remoteError) ?></div>
<?php endif; ?>

<section class="bgaming-fs-guide">
    <h2>Freespin yaşam döngüsü</h2>
    <ol class="bgaming-fs-steps">
        <li><b><span class="bgaming-fs-step-badge">1</span>Kampanyadan ekle</b><span>Freespin verme işlemi <a href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Freespin Kampanyaları</a> ekranından yapılır; her kullanıcı için tek atama kaydı oluşur.</span></li>
        <li><b><span class="bgaming-fs-step-badge">2</span>Durum senkronu</b><span>Issue kimliğiyle sağlayıcıdan güncel durumu (active / played / canceled / expired) çekip atama kaydına işler.</span></li>
        <li><b><span class="bgaming-fs-step-badge">3</span>İptal</b><span>Henüz oynanmamış freespin iptal edilebilir; oynanan freespin iptal edilemez.</span></li>
        <li><b><span class="bgaming-fs-step-badge">4</span>Kazanç</b><span>Spinler bitince BGaming <code>/freespins/finish</code> çağırır; kazanç bakiyeye tek sefer eklenir ve durum "Oynandı" olur.</span></li>
    </ol>
</section>

<div class="bgaming-fs-grid">
    <div class="bgaming-fs-stack">
        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2 style="margin:0;font-size:18px">Issue Yönetimi</h2>
            </div>
            <div class="bgaming-fs-body">
                <div class="bgaming-fs-inline">
                    <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/sync')) ?>">
                        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                        <div class="field">
                            <label class="field-label" for="sync_issue_id">Issue kimliği</label>
                            <input id="sync_issue_id" class="input bgaming-fs-mono" type="text" name="issue_id" required placeholder="fs_...">
                        </div>
                        <button class="btn btn--secondary admin-full-action" type="submit">Durumu Senkronize Et</button>
                    </form>
                    <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/cancel')) ?>" onsubmit="return confirm('Bu freespin iptal edilsin mi?');">
                        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                        <div class="field">
                            <label class="field-label" for="cancel_issue_id">Issue kimliği</label>
                            <input id="cancel_issue_id" class="input bgaming-fs-mono" type="text" name="issue_id" required placeholder="fs_...">
                        </div>
                        <button class="btn btn--danger admin-full-action" type="submit">Issue İptal Et</button>
                    </form>
                </div>
                <p class="bgaming-fs-meta" style="margin-top:12px">Issue kimliğini alt taraftaki atama tablosundan kopyalayabilirsiniz.</p>
            </div>
        </section>

        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2 style="margin:0;font-size:18px">Kullanıcı Atamaları</h2>
            </div>
            <div class="bgaming-fs-body" style="padding-top:4px">
                <table class="bgaming-fs-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Kampanya / Issue</th>
                            <th>Durum</th>
                            <th>Spin</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($assignments === []): ?>
                            <tr><td colspan="5" class="bgaming-fs-meta">Henüz freespin ataması yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $row): ?>
                                <?php
                                $status = strtolower((string) ($row['status'] ?? ''));
                                $lastError = trim((string) ($row['last_error'] ?? ''));
                                ?>
                                <tr>
                                    <td>
                                        #<?= (int) ($row['user_id'] ?? 0) ?>
                                        <?php if (!empty($row['username'])): ?><div class="bgaming-fs-meta"><?= $text($row['username']) ?></div><?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $text($row['title'] ?? '') ?>
                                        <div class="bgaming-fs-meta bgaming-fs-mono"><?= $text($row['remote_issue_id'] ?? '-') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge dot <?= $text($statusBadgeClass($status)) ?>"><?= $text($row['status_label'] ?? $status) ?></span>
                                        <?php if ($lastError !== ''): ?><div class="bgaming-fs-error"><?= $text($lastError) ?></div><?php endif; ?>
                                    </td>
                                    <td><?= (int) ($row['freespins_done'] ?? 0) ?> / <?= (int) ($row['freespins_total'] ?? 0) ?></td>
                                    <td>
                                        <div class="bgaming-fs-actions">
                                            <?php if (in_array($status, ['failed', 'pending'], true)): ?>
                                                <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assignments/retry')) ?>">
                                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                                    <input type="hidden" name="assignment_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                                    <input type="hidden" name="return" value="freespins">
                                                    <button class="btn btn--secondary" type="submit">Tekrar Dene</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($status === 'active'): ?>
                                                <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assignments/cancel')) ?>" onsubmit="return confirm('Bu freespin iptal edilsin mi?');">
                                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                                    <input type="hidden" name="assignment_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                                    <input type="hidden" name="return" value="freespins">
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
    </div>

    <div class="bgaming-fs-stack">
        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2 style="margin:0;font-size:18px">Sağlayıcıdaki Freespinler</h2>
            </div>
            <div class="bgaming-fs-body">
                <form method="get" action="<?= $text(AdminAuth::url('/bgaming/freespins')) ?>" class="bgaming-fs-inline" style="margin-bottom:14px">
                    <div class="field">
                        <label class="field-label" for="filter_user_id">Kullanıcı ID</label>
                        <input id="filter_user_id" class="input" type="number" name="user_id" min="0" value="<?= (int) ($remoteFilter['user_id'] ?? 0) ?>">
                    </div>
                    <div class="field">
                        <label class="field-label" for="filter_status">Durum</label>
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
                        <label class="field-label" for="filter_page">Sayfa</label>
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
                            <th>Kazanç</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($remoteItems === []): ?>
                            <tr><td colspan="5" class="bgaming-fs-meta">Sağlayıcıda freespin kaydı bulunamadı.</td></tr>
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
                <h2 style="margin:0;font-size:18px">Gelişmiş: Kampanyasız Issue</h2>
            </div>
            <div class="bgaming-fs-body">
                <details class="bgaming-fs-advanced">
                    <summary>API kabul testi için doğrudan issue gönder</summary>
                    <p class="bgaming-fs-hint" style="margin:10px 0 14px">
                        Normal freespin verme işlemi için <a href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Freespin Kampanyaları</a> ekranını kullanın.
                        Buradan gönderilen issue kampanyaya bağlanmaz; sağlayıcı kaynaklı kayıt olarak listelenir.
                    </p>
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
                                <label class="field-label" for="fs_issue_id">Issue kimliği (opsiyonel)</label>
                                <input id="fs_issue_id" class="input bgaming-fs-mono" type="text" name="issue_id" placeholder="Boşsa otomatik üretilir">
                            </div>
                            <div class="field">
                                <label class="field-label" for="fs_games">Oyun</label>
                                <?php if ($freespinGames !== []): ?>
                                    <select id="fs_games" class="input" name="games" required>
                                        <option value="">Freespin destekli oyun seçin</option>
                                        <option value="acceptance:test">acceptance:test (API testi)</option>
                                        <?php foreach ($freespinGames as $game): ?>
                                            <?php
                                            $identifier = (string) ($game['identifier'] ?? '');
                                            $title = (string) ($game['title'] ?? $identifier);
                                            ?>
                                            <option value="<?= $text($identifier) ?>"><?= $text($title) ?> (<?= $text($identifier) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input id="fs_games" class="input" type="text" name="games" required placeholder="acceptance:test">
                                <?php endif; ?>
                            </div>
                            <div class="field">
                                <label class="field-label" for="fs_count">Freespin adedi</label>
                                <input id="fs_count" class="input" type="number" name="freespins_quantity" min="1" max="1000" value="10" required>
                            </div>
                            <div class="field">
                                <label class="field-label" for="fs_currency">Para birimi</label>
                                <input id="fs_currency" class="input" type="text" name="currency" value="<?= $text($configRow['currency'] ?? 'USD') ?>" maxlength="8">
                            </div>
                            <div class="field">
                                <label class="field-label" for="fs_bet_level">Bet seviyesi (opsiyonel)</label>
                                <input id="fs_bet_level" class="input" type="number" name="bet_level" min="0" step="1" value="0">
                            </div>
                            <div class="field">
                                <label class="field-label" for="fs_valid_since">Geçerlilik başlangıcı (opsiyonel)</label>
                                <input id="fs_valid_since" class="input admin-date-input" type="datetime-local" name="valid_since">
                            </div>
                            <div class="field">
                                <label class="field-label" for="fs_valid_until">Geçerlilik bitişi</label>
                                <input id="fs_valid_until" class="input admin-date-input" type="datetime-local" name="valid_until" required>
                            </div>
                        </div>
                        <div class="form-actions admin-action-spaced">
                            <button class="btn btn--secondary" type="submit">Issue Gönder</button>
                        </div>
                    </form>
                </details>
            </div>
        </section>
    </div>
</div>
