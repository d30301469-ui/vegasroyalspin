<?php

$userSettings = is_array($userSettings ?? null) ? $userSettings : [];
$userRow = is_array($userRow ?? null) ? $userRow : null;
$recentRows = is_array($recentRows ?? null) ? $recentRows : [];
$configRow = is_array($configRow ?? null) ? $configRow : [];
$context = is_array($context ?? null) ? $context : [];
$vendors = is_array($vendors ?? null) ? $vendors : [];
$games = is_array($games ?? null) ? $games : [];
$flash = trim((string) ($flash ?? ''));
$lookup = trim((string) ($lookup ?? ''));
$userCode = trim((string) ($userCode ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$isActive = !empty($configRow['is_active']) && $configRow['is_active'] !== '0';
$vendorCode = (string) ($context['vendor_code'] ?? '');
$gameCode = (string) ($context['game_code'] ?? '');
$currencyCode = (string) ($context['currency_code'] ?? 'TRY');
$hasScope = $vendorCode !== '' && $gameCode !== '';
$lowRtp = (string) ($userSettings['LowRtp'] ?? '');
$highRtp = (string) ($userSettings['HighRtp'] ?? '');
?>
<style>
    .ca-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 18px; align-items: start; }
    .ca-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .ca-actions { display: flex; flex-direction: column; gap: 10px; }
    .ca-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; margin-top: 6px; }
    .ca-stat { display: flex; justify-content: space-between; gap: 14px; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
    .ca-stat:last-child { border-bottom: 0; }
    .ca-section-title { font-weight:700; margin: 0 0 12px; }
    .ca-table { width:100%; border-collapse: collapse; font-size: 13px; }
    .ca-table th, .ca-table td { text-align:left; padding:8px 6px; border-bottom:1px solid var(--border); }
    .ca-scope { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; }
    .ca-search-row { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
    .ca-search-row .field { flex: 1; min-width: 160px; margin-bottom: 0; }
    @media (max-width: 900px) { .ca-grid, .ca-scope { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Game Control API · ChangeUserSetting</span>
        <h1 class="hero-title">Kullanıcı <span class="accent">RTP</span></h1>
        <p class="hero-sub">LowRtp / HighRtp — userCode + vendor + game + currency zorunlu.</p>
    </div>
</section>

<?php if ($flash !== ''): ?><div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div><?php endif; ?>
<?php if (!$isActive): ?><div class="alert alert--warning" style="margin-bottom:16px">Aggregator pasif.</div><?php endif; ?>

<div class="ca-grid">
    <div style="display:flex;flex-direction:column;gap:18px;">
        <form class="ca-card" method="get" action="<?= $text(AdminAuth::url('/casino-aggregator/user-settings')) ?>">
            <div class="ca-section-title">Kullanıcı + kapsam</div>
            <div class="ca-search-row" style="margin-bottom:12px">
                <div class="field">
                    <label class="field-label" for="user">user (ID / username)</label>
                    <input id="user" class="input" type="text" name="user" value="<?= $text($lookup) ?>">
                </div>
            </div>
            <div class="ca-scope">
                <div class="field">
                    <label class="field-label" for="vendor_code">vendorCode</label>
                    <select id="vendor_code" class="input" name="vendor_code" onchange="this.form.submit()">
                        <option value="">— seç —</option>
                        <?php foreach ($vendors as $v): ?>
                            <?php $code = (string) ($v['vendor_code'] ?? ''); ?>
                            <option value="<?= $text($code) ?>" <?= $code === $vendorCode ? 'selected' : '' ?>><?= $text(($v['vendor_name'] ?? $code) . ' (' . $code . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="game_code">gameCode</label>
                    <select id="game_code" class="input" name="game_code">
                        <option value="">— seç —</option>
                        <?php foreach ($games as $g): ?>
                            <?php $code = (string) ($g['game_code'] ?? ''); ?>
                            <option value="<?= $text($code) ?>" <?= $code === $gameCode ? 'selected' : '' ?>><?= $text(($g['game_name'] ?? $code) . ' (' . $code . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="currency_code">currencyCode</label>
                    <input id="currency_code" class="input" type="text" name="currency_code" value="<?= $text($currencyCode) ?>" maxlength="8">
                </div>
            </div>
            <button class="btn btn--primary" type="submit" style="margin-top:12px">Getir</button>
        </form>

        <?php if ($userRow !== null && $hasScope): ?>
            <form class="ca-card" method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/user-settings')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <input type="hidden" name="user_code" value="<?= $text($userCode) ?>">
                <input type="hidden" name="vendor_code" value="<?= $text($vendorCode) ?>">
                <input type="hidden" name="game_code" value="<?= $text($gameCode) ?>">
                <input type="hidden" name="currency_code" value="<?= $text($currencyCode) ?>">
                <div class="ca-section-title"><?= $text((string) ($userRow['username'] ?? '')) ?> <span style="color:var(--t-muted);font-weight:500">#<?= (int) ($userRow['id'] ?? 0) ?></span></div>
                <div class="field">
                    <label class="field-label" for="LowRtp">LowRtp</label>
                    <input id="LowRtp" class="input" type="text" name="LowRtp" value="<?= $text($lowRtp) ?>" placeholder="0.5">
                </div>
                <div class="field">
                    <label class="field-label" for="HighRtp">HighRtp</label>
                    <input id="HighRtp" class="input" type="text" name="HighRtp" value="<?= $text($highRtp) ?>" placeholder="0.8">
                </div>
                <button class="btn btn--primary" type="submit">ChangeUserSetting</button>
            </form>
            <form class="ca-card" method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/user-settings/pull')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <input type="hidden" name="user_code" value="<?= $text($userCode) ?>">
                <input type="hidden" name="vendor_code" value="<?= $text($vendorCode) ?>">
                <input type="hidden" name="game_code" value="<?= $text($gameCode) ?>">
                <input type="hidden" name="currency_code" value="<?= $text($currencyCode) ?>">
                <button class="btn btn--ghost" type="submit">GetUserSetting</button>
            </form>
        <?php elseif ($lookup !== '' && $userRow === null): ?>
            <div class="ca-card"><div class="alert alert--warning" style="margin:0">Kullanıcı yok: <?= $text($lookup) ?></div></div>
        <?php elseif ($userRow !== null && !$hasScope): ?>
            <div class="ca-card"><p class="ca-help" style="margin:0">vendorCode ve gameCode seçin.</p></div>
        <?php endif; ?>

        <div class="ca-card">
            <div class="ca-section-title">Son kayıtlar</div>
            <?php if ($recentRows === []): ?>
                <p class="ca-help" style="margin:0">Kayıt yok.</p>
            <?php else: ?>
                <table class="ca-table">
                    <thead><tr><th>User</th><th>Scope</th><th>Cat</th><th>Value</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recentRows as $row): ?>
                        <?php $rowCode = (string) ($row['user_code'] ?? ''); ?>
                        <tr>
                            <td><?= $text((string) ($row['username'] ?? $rowCode)) ?></td>
                            <td><?= $text(($row['vendor_code'] ?? '') . '/' . ($row['game_code'] ?? '')) ?></td>
                            <td><?= $text((string) ($row['category'] ?? $row['setting_key'] ?? '')) ?></td>
                            <td><?= $text((string) ($row['setting_value'] ?? '')) ?></td>
                            <td><a class="btn btn--xs" href="<?= $text(AdminAuth::url('/casino-aggregator/user-settings?' . http_build_query([
                                'user' => $rowCode,
                                'vendor_code' => (string) ($row['vendor_code'] ?? ''),
                                'game_code' => (string) ($row['game_code'] ?? ''),
                                'currency_code' => (string) ($row['currency_code'] ?? $currencyCode),
                            ]))) ?>">Aç</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="ca-actions">
        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:10px">UserSetting</div>
            <div class="ca-stat"><span>LowRtp</span><strong>category</strong></div>
            <div class="ca-stat"><span>HighRtp</span><strong>category</strong></div>
            <p class="ca-help">API key alanı boş string gönderilir.</p>
        </div>
    </div>
</div>
