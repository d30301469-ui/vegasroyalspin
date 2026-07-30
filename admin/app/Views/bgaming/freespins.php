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

$statusTr = static fn (string $status): string => match (strtolower(trim($status))) {
    'active' => 'Aktif',
    'played' => 'Oynandı',
    'canceled' => 'İptal',
    'expired' => 'Süresi doldu',
    'pending' => 'Hazırlanıyor',
    'failed' => 'Başarısız',
    default => $status !== '' ? $status : '-',
};

$recentUsers = array_slice($users, 0, 40);
?>
<style>
    .bgaming-fs-grid { display:grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap:18px; align-items:start; }
    .bgaming-fs-card { background: var(--bg-card); border:1px solid var(--border); border-radius:18px; box-shadow: var(--shadow-card); }
    .bgaming-fs-head { padding:18px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    .bgaming-fs-head h2 { margin:0; font-size:18px; }
    .bgaming-fs-body { padding:18px 20px; }
    .bgaming-fs-meta { color:var(--t-muted); font-size:13px; }
    .bgaming-fs-error { color: var(--danger, #e5484d); font-size:12px; }
    .bgaming-fs-stack { display:flex; flex-direction:column; gap:16px; }
    .bgaming-fs-inline { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .bgaming-fs-actions { display:flex; gap:6px; flex-wrap:wrap; }
    .bgaming-fs-actions form { display:inline; }
    .bgaming-fs-hint { color: var(--t-muted); font-size:12px; margin:4px 0 0; }
    @media (max-width: 1080px) { .bgaming-fs-grid, .bgaming-fs-inline { grid-template-columns:1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · BGaming</span>
        <h1 class="hero-title">BGaming <span class="accent">Freespin Listesi</span></h1>
        <p class="hero-sub">Tanımlanan freespinleri takip edin, iptal edin veya durumunu güncelleyin.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">BGaming Ayarları</a>
        <a class="btn btn--primary" href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Freespin Oluştur</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info admin-alert-spaced"><?= $text($flash) ?></div>
<?php endif; ?>

<?php if ($remoteError !== ''): ?>
    <div class="alert alert--error admin-alert-spaced">BGaming listesi alınamadı: <?= $text($remoteError) ?></div>
<?php endif; ?>

<div class="bgaming-fs-grid">
    <div class="bgaming-fs-stack">
        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2>Tanımlanan freespinler</h2>
                <span class="badge primary"><?= count($assignments) ?></span>
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
                        <?php if ($assignments === []): ?>
                            <tr>
                                <td colspan="5">
                                    Kayıt yok.
                                    <a href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Freespin Oluştur</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $row): ?>
                                <?php
                                $status = strtolower((string) ($row['status'] ?? ''));
                                $lastError = trim((string) ($row['last_error'] ?? ''));
                                $expires = (int) ($row['valid_until'] ?? $row['expires_at'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <?= $text($row['username'] ?? ('#' . (int) ($row['user_id'] ?? 0))) ?>
                                        <div class="bgaming-fs-meta">#<?= (int) ($row['user_id'] ?? 0) ?></div>
                                    </td>
                                    <td>
                                        <?= $text($row['title'] ?? 'Freespin') ?>
                                        <?php if (!empty($row['game_identifier'])): ?>
                                            <div class="bgaming-fs-meta"><?= $text($row['game_identifier']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($expires > 0): ?>
                                            <div class="bgaming-fs-meta">Son: <?= $text(date('d.m.Y H:i', $expires)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $text($statusBadgeClass($status)) ?>"><?= $text($row['status_label'] ?? $statusTr($status)) ?></span>
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
                                                    <button class="btn btn--ghost" style="font-size:11px;padding:4px 8px" type="submit">Tekrar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($status === 'active'): ?>
                                                <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assignments/cancel')) ?>" onsubmit="return confirm('İptal edilsin mi?');">
                                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                                    <input type="hidden" name="assignment_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                                    <input type="hidden" name="return" value="freespins">
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

        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2>Durum işlemleri</h2>
            </div>
            <div class="bgaming-fs-body">
                <div class="bgaming-fs-inline">
                    <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/sync')) ?>">
                        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                        <div class="field">
                            <label class="field-label" for="sync_issue_id">Kod</label>
                            <input id="sync_issue_id" class="input" type="text" name="issue_id" required placeholder="fs_...">
                        </div>
                        <button class="btn btn--secondary admin-full-action" type="submit">Durumu Güncelle</button>
                    </form>
                    <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/cancel')) ?>" onsubmit="return confirm('İptal edilsin mi?');">
                        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                        <div class="field">
                            <label class="field-label" for="cancel_issue_id">Kod</label>
                            <input id="cancel_issue_id" class="input" type="text" name="issue_id" required placeholder="fs_...">
                        </div>
                        <button class="btn btn--danger admin-full-action" type="submit">İptal Et</button>
                    </form>
                </div>
            </div>
        </section>
    </div>

    <div class="bgaming-fs-stack">
        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2>BGaming kayıtları</h2>
            </div>
            <div class="bgaming-fs-body">
                <form method="get" action="<?= $text(AdminAuth::url('/bgaming/freespins')) ?>" class="bgaming-fs-inline" style="margin-bottom:14px">
                    <div class="field">
                        <label class="field-label" for="filter_user_id">Oyuncu ID</label>
                        <input id="filter_user_id" class="input" type="number" name="user_id" min="0" value="<?= (int) ($remoteFilter['user_id'] ?? 0) ?>">
                    </div>
                    <div class="field">
                        <label class="field-label" for="filter_status">Durum</label>
                        <select id="filter_status" class="input" name="status">
                            <?php $statusFilter = (string) ($remoteFilter['status'] ?? ''); ?>
                            <option value=""<?= $statusFilter === '' ? ' selected' : '' ?>>Hepsi</option>
                            <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>>Aktif</option>
                            <option value="played"<?= $statusFilter === 'played' ? ' selected' : '' ?>>Oynandı</option>
                            <option value="canceled"<?= $statusFilter === 'canceled' ? ' selected' : '' ?>>İptal</option>
                            <option value="expired"<?= $statusFilter === 'expired' ? ' selected' : '' ?>>Süresi doldu</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="filter_page">Sayfa</label>
                        <input id="filter_page" class="input" type="number" name="page" min="1" value="<?= (int) ($remoteFilter['page'] ?? 1) ?>">
                    </div>
                    <div class="field" style="align-self:end">
                        <button class="btn btn--ghost admin-full-action" type="submit">Filtrele</button>
                    </div>
                </form>
            </div>
            <div class="admin-compact-table-wrap">
                <table class="admin-compact-table">
                    <thead>
                        <tr>
                            <th>Oyuncu</th>
                            <th>Durum</th>
                            <th>Spin</th>
                            <th>Kazanç</th>
                            <th>Kod</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($remoteItems === []): ?>
                            <tr><td colspan="5">Kayıt bulunamadı.</td></tr>
                        <?php else: ?>
                            <?php foreach ($remoteItems as $item): ?>
                                <tr>
                                    <td>#<?= (int) ($item['user_id'] ?? 0) ?></td>
                                    <td><?= $text($statusTr((string) ($item['status'] ?? ''))) ?></td>
                                    <td><?= (int) ($item['freespins_done'] ?? 0) ?> / <?= (int) ($item['freespins_quantity'] ?? $item['freespins_count'] ?? 0) ?></td>
                                    <td><?= (int) ($item['win_amount'] ?? 0) ?></td>
                                    <td class="bgaming-fs-meta"><?= $text($item['issue_id'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($remoteMeta !== []): ?>
                <div class="bgaming-fs-body" style="padding-top:0">
                    <p class="bgaming-fs-meta">
                        Toplam: <?= (int) ($remoteMeta['total'] ?? 0) ?> ·
                        Sayfa: <?= (int) ($remoteMeta['page'] ?? 1) ?> / <?= (int) ($remoteMeta['last_page'] ?? 1) ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <section class="bgaming-fs-card">
            <div class="bgaming-fs-head">
                <h2>Test gönderimi</h2>
            </div>
            <div class="bgaming-fs-body">
                <p class="bgaming-fs-hint" style="margin-top:0;margin-bottom:12px">Normal kullanım için Freespin Oluştur ekranını kullanın.</p>
                <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/issue')) ?>">
                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                    <div class="bgaming-fs-inline">
                        <div class="field">
                            <label class="field-label" for="fs_user_id">Oyuncu</label>
                            <select id="fs_user_id" class="input" name="user_id" required>
                                <option value="">Seçin</option>
                                <?php foreach ($recentUsers as $user): ?>
                                    <option value="<?= (int) ($user['id'] ?? 0) ?>">#<?= (int) ($user['id'] ?? 0) ?> · <?= $text($user['username'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_games">Oyun</label>
                            <?php if ($freespinGames !== []): ?>
                                <select id="fs_games" class="input" name="games" required>
                                    <option value="">Seçin</option>
                                    <option value="acceptance:test">acceptance:test</option>
                                    <?php foreach ($freespinGames as $game): ?>
                                        <option value="<?= $text($game['identifier'] ?? '') ?>"><?= $text($game['title'] ?? $game['identifier'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input id="fs_games" class="input" type="text" name="games" required>
                            <?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_count">Spin</label>
                            <input id="fs_count" class="input" type="number" name="freespins_quantity" min="1" max="1000" value="10" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_valid_until">Bitiş</label>
                            <input id="fs_valid_until" class="input admin-date-input" type="datetime-local" name="valid_until" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="fs_currency">Para birimi</label>
                            <input id="fs_currency" class="input" type="text" name="currency" value="<?= $text($configRow['currency'] ?? 'USD') ?>" maxlength="8">
                        </div>
                    </div>
                    <div class="form-actions admin-action-spaced">
                        <button class="btn btn--secondary" type="submit">Test Gönder</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
