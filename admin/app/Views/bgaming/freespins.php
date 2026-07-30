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
?>
<style>
    .bg-track-card { background: var(--bg-card); border:1px solid var(--border); border-radius:16px; box-shadow: var(--shadow-card); margin-bottom:16px; }
    .bg-track-head { padding:16px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap; }
    .bg-track-head h2 { margin:0; font-size:17px; }
    .bg-track-body { padding:18px; }
    .bg-track-table { width:100%; border-collapse:collapse; }
    .bg-track-table th, .bg-track-table td { padding:10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .bg-track-table th { color:var(--t-muted); font-size:12px; text-transform:uppercase; letter-spacing:.05em; }
    .bg-track-meta { color:var(--t-muted); font-size:12px; }
    .bg-track-error { color:var(--danger,#e5484d); font-size:12px; margin-top:4px; }
    .bg-track-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .bg-track-actions form { display:inline; }
    .bg-track-filters { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
    .bg-track-legend { display:flex; flex-wrap:wrap; gap:10px 16px; margin:0; padding:0; list-style:none; color:var(--t-muted); font-size:13px; }
    .bg-track-legend li strong { color:inherit; }
    .bg-track-adv summary { cursor:pointer; color:var(--t-muted); font-size:13px; }
    .bg-track-adv-body { margin-top:14px; }
    .bg-track-inline { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    @media (max-width:900px) { .bg-track-filters, .bg-track-inline { grid-template-columns:1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">BGaming</span>
        <h1 class="hero-title"><span class="accent">Verilen Freespinler</span></h1>
        <p class="hero-sub">Kim ne aldı, oynadı mı, iptal mi edildi — buradan takip edin.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--primary" href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Freespin Ver</a>
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">Ayarlar</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info admin-alert-spaced"><?= $text($flash) ?></div>
<?php endif; ?>

<?php if ($remoteError !== ''): ?>
    <div class="alert alert--error admin-alert-spaced">BGaming listesi alınamadı: <?= $text($remoteError) ?></div>
<?php endif; ?>

<section class="bg-track-card">
    <div class="bg-track-body">
        <ul class="bg-track-legend">
            <li><strong>Aktif</strong> = oyuncu kullanabilir</li>
            <li><strong>Oynandı</strong> = spinler bitti, kazanç işlendi</li>
            <li><strong>Başarısız</strong> = gönderilemedi, Tekrar Dene</li>
            <li><strong>İptal / Süresi doldu</strong> = kullanılamaz</li>
        </ul>
    </div>
</section>

<section class="bg-track-card">
    <div class="bg-track-head">
        <h2>Oyunculara verilen freespinler</h2>
    </div>
    <div class="bg-track-body" style="padding-top:4px">
        <table class="bg-track-table">
            <thead>
                <tr>
                    <th>Oyuncu</th>
                    <th>Ne verildi?</th>
                    <th>Durum</th>
                    <th>Kullanılan / Toplam</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($assignments === []): ?>
                    <tr>
                        <td colspan="5" class="bg-track-meta">
                            Henüz kayıt yok.
                            <a href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Freespin Ver</a> ekranından ilk kaydı oluşturun.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assignments as $row): ?>
                        <?php
                        $status = strtolower((string) ($row['status'] ?? ''));
                        $lastError = trim((string) ($row['last_error'] ?? ''));
                        $title = (string) ($row['title'] ?? 'Freespin');
                        $game = (string) ($row['game_identifier'] ?? '');
                        $expires = (int) ($row['valid_until'] ?? $row['expires_at'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <?= $text($row['username'] ?? ('#' . (int) ($row['user_id'] ?? 0))) ?>
                                <div class="bg-track-meta">#<?= (int) ($row['user_id'] ?? 0) ?></div>
                            </td>
                            <td>
                                <?= $text($title) ?>
                                <?php if ($game !== ''): ?><div class="bg-track-meta"><?= $text($game) ?></div><?php endif; ?>
                                <?php if ($expires > 0): ?><div class="bg-track-meta">Son: <?= $text(date('d.m.Y H:i', $expires)) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge dot <?= $text($statusBadgeClass($status)) ?>"><?= $text($row['status_label'] ?? $statusTr($status)) ?></span>
                                <?php if ($lastError !== ''): ?><div class="bg-track-error"><?= $text($lastError) ?></div><?php endif; ?>
                            </td>
                            <td><?= (int) ($row['freespins_done'] ?? 0) ?> / <?= (int) ($row['freespins_total'] ?? 0) ?></td>
                            <td>
                                <div class="bg-track-actions">
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
                                    <?php if (!in_array($status, ['failed', 'pending', 'active'], true)): ?>
                                        <span class="bg-track-meta">—</span>
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

<section class="bg-track-card">
    <div class="bg-track-head">
        <h2>BGaming tarafındaki kayıtlar</h2>
        <span class="bg-track-meta">Karşı kontrol için</span>
    </div>
    <div class="bg-track-body">
        <form method="get" action="<?= $text(AdminAuth::url('/bgaming/freespins')) ?>" class="bg-track-filters">
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

        <table class="bg-track-table">
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
                    <tr><td colspan="5" class="bg-track-meta">BGaming'de görünen kayıt yok.</td></tr>
                <?php else: ?>
                    <?php foreach ($remoteItems as $item): ?>
                        <tr>
                            <td>#<?= (int) ($item['user_id'] ?? 0) ?></td>
                            <td><?= $text($statusTr((string) ($item['status'] ?? ''))) ?></td>
                            <td><?= (int) ($item['freespins_done'] ?? 0) ?> / <?= (int) ($item['freespins_quantity'] ?? $item['freespins_count'] ?? 0) ?></td>
                            <td><?= (int) ($item['win_amount'] ?? 0) ?></td>
                            <td class="bg-track-meta"><?= $text($item['issue_id'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($remoteMeta !== []): ?>
            <p class="bg-track-meta" style="margin-top:10px">
                Toplam: <?= (int) ($remoteMeta['total'] ?? 0) ?> ·
                Sayfa: <?= (int) ($remoteMeta['page'] ?? 1) ?> / <?= (int) ($remoteMeta['last_page'] ?? 1) ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="bg-track-card">
    <div class="bg-track-body">
        <details class="bg-track-adv">
            <summary>Teknik işlemler (gelişmiş)</summary>
            <div class="bg-track-adv-body">
                <p class="bg-track-meta" style="margin-top:0">
                    Normal kullanımda yukarıdaki tablodan İptal / Tekrar Dene yeterlidir. Bu bölüm test ve sorun giderme içindir.
                </p>
                <div class="bg-track-inline" style="margin-bottom:16px">
                    <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/sync')) ?>">
                        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                        <div class="field">
                            <label class="field-label" for="sync_issue_id">Kod ile durum güncelle</label>
                            <input id="sync_issue_id" class="input" type="text" name="issue_id" required placeholder="fs_...">
                        </div>
                        <button class="btn btn--secondary admin-full-action" type="submit">Durumu Çek</button>
                    </form>
                    <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/cancel')) ?>" onsubmit="return confirm('Bu freespin iptal edilsin mi?');">
                        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                        <div class="field">
                            <label class="field-label" for="cancel_issue_id">Kod ile iptal</label>
                            <input id="cancel_issue_id" class="input" type="text" name="issue_id" required placeholder="fs_...">
                        </div>
                        <button class="btn btn--danger admin-full-action" type="submit">İptal Et</button>
                    </form>
                </div>

                <details>
                    <summary class="bg-track-meta">API test: kampanyasız freespin gönder</summary>
                    <form method="post" action="<?= $text(AdminAuth::url('/bgaming/freespins/issue')) ?>" style="margin-top:12px">
                        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                        <div class="bg-track-inline">
                            <div class="field">
                                <label class="field-label" for="fs_user_id">Oyuncu</label>
                                <select id="fs_user_id" class="input" name="user_id" required>
                                    <option value="">Seçin</option>
                                    <?php foreach ($users as $user): ?>
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
                                <label class="field-label" for="fs_valid_until">Son tarih</label>
                                <input id="fs_valid_until" class="input admin-date-input" type="datetime-local" name="valid_until" required>
                            </div>
                            <div class="field">
                                <label class="field-label" for="fs_currency">Para birimi</label>
                                <input id="fs_currency" class="input" type="text" name="currency" value="<?= $text($configRow['currency'] ?? 'USD') ?>" maxlength="8">
                            </div>
                            <div class="field">
                                <label class="field-label" for="fs_issue_id">Kod (opsiyonel)</label>
                                <input id="fs_issue_id" class="input" type="text" name="issue_id">
                            </div>
                        </div>
                        <div class="form-actions admin-action-spaced">
                            <button class="btn btn--secondary" type="submit">Test Gönder</button>
                        </div>
                    </form>
                </details>
            </div>
        </details>
    </div>
</section>
