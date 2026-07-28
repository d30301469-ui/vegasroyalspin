<?php

$agentSettings = is_array($agentSettings ?? null) ? $agentSettings : [];
$configRow = is_array($configRow ?? null) ? $configRow : [];
$context = is_array($context ?? null) ? $context : [];
$vendors = is_array($vendors ?? null) ? $vendors : [];
$games = is_array($games ?? null) ? $games : [];
$responseCodes = is_array($responseCodes ?? null) ? $responseCodes : [];
$gameTypes = is_array($gameTypes ?? null) ? $gameTypes : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$isActive = !empty($configRow['is_active']) && $configRow['is_active'] !== '0';
$vendorCode = (string) ($context['vendor_code'] ?? '');
$gameCode = (string) ($context['game_code'] ?? '');
$currencyCode = (string) ($context['currency_code'] ?? 'TRY');
$hasScope = $vendorCode !== '' && $gameCode !== '';
$roundKey = (string) ($agentSettings['RoundKey'] ?? '');
$lowRtp = (string) ($agentSettings['LowRtp'] ?? '');
$highRtp = (string) ($agentSettings['HighRtp'] ?? '');
$hideRoundId = ((string) ($agentSettings['HideRoundId'] ?? '0')) === '1';
$hideTournament = ((string) ($agentSettings['HideTournament'] ?? '0')) === '1';
$hideBadge = ((string) ($agentSettings['HideBadge'] ?? '0')) === '1';
?>
<style>
    .ca-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 18px; align-items: start; }
    .ca-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .ca-actions { display: flex; flex-direction: column; gap: 10px; }
    .ca-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; margin-top: 6px; }
    .ca-stat { display: flex; justify-content: space-between; gap: 14px; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
    .ca-stat:last-child { border-bottom: 0; }
    .ca-toggle { display:flex; align-items:center; gap:10px; padding:10px 0; }
    .ca-code-table { width:100%; border-collapse: collapse; font-size: 12px; }
    .ca-code-table th, .ca-code-table td { text-align:left; padding:6px 4px; border-bottom:1px solid var(--border); vertical-align: top; }
    .ca-section-title { font-weight:700; margin: 18px 0 8px; }
    .ca-section-title:first-child { margin-top: 0; }
    .ca-scope { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; }
    @media (max-width: 900px) { .ca-grid, .ca-scope { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Game Control API · ChangeAgentSetting</span>
        <h1 class="hero-title">Agent <span class="accent">Kontrolleri</span></h1>
        <p class="hero-sub">category + boş key; vendor/game/currency zorunlu (API v1.0.0).</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/casino-aggregator/game-control')) ?>">Game Control</a>
        <?php if ($hasScope): ?>
            <button class="btn btn--primary" type="submit" form="caAgentSettingsForm">ChangeAgentSetting</button>
        <?php endif; ?>
    </div>
</section>

<?php if ($flash !== ''): ?><div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div><?php endif; ?>
<?php if (!$isActive): ?><div class="alert alert--warning" style="margin-bottom:16px">Aggregator pasif — önce ayarlardan aktif edin.</div><?php endif; ?>

<div class="ca-grid">
    <div style="display:flex;flex-direction:column;gap:18px;">
        <form class="ca-card" method="get" action="<?= $text(AdminAuth::url('/casino-aggregator/agent-settings')) ?>">
            <div class="ca-section-title">Kapsam (vendor / game / currency)</div>
            <div class="ca-scope">
                <div class="field">
                    <label class="field-label" for="vendor_code">vendorCode</label>
                    <select id="vendor_code" class="input" name="vendor_code" onchange="this.form.submit()">
                        <option value="">— seç —</option>
                        <?php foreach ($vendors as $v): ?>
                            <?php $code = (string) ($v['vendor_code'] ?? ''); ?>
                            <option value="<?= $text($code) ?>" <?= $code === $vendorCode ? 'selected' : '' ?>>
                                <?= $text(($v['vendor_name'] ?? $code) . ' (' . $code . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="game_code">gameCode</label>
                    <select id="game_code" class="input" name="game_code">
                        <option value="">— seç —</option>
                        <?php foreach ($games as $g): ?>
                            <?php $code = (string) ($g['game_code'] ?? ''); ?>
                            <option value="<?= $text($code) ?>" <?= $code === $gameCode ? 'selected' : '' ?>>
                                <?= $text(($g['game_name'] ?? $code) . ' (' . $code . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="currency_code">currencyCode</label>
                    <input id="currency_code" class="input" type="text" name="currency_code" value="<?= $text($currencyCode) ?>" maxlength="8">
                </div>
            </div>
            <button class="btn btn--ghost" type="submit" style="margin-top:12px">Yükle</button>
        </form>

        <?php if ($hasScope): ?>
        <form id="caAgentSettingsForm" class="ca-card" method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/agent-settings')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <input type="hidden" name="vendor_code" value="<?= $text($vendorCode) ?>">
            <input type="hidden" name="game_code" value="<?= $text($gameCode) ?>">
            <input type="hidden" name="currency_code" value="<?= $text($currencyCode) ?>">

            <div class="ca-section-title">AgentSetting categories (key = boş)</div>
            <div class="field">
                <label class="field-label" for="LowRtp">LowRtp</label>
                <input id="LowRtp" class="input" type="text" name="LowRtp" value="<?= $text($lowRtp) ?>" placeholder="0.5">
            </div>
            <div class="field">
                <label class="field-label" for="HighRtp">HighRtp</label>
                <input id="HighRtp" class="input" type="text" name="HighRtp" value="<?= $text($highRtp) ?>" placeholder="0.8">
            </div>
            <div class="field">
                <label class="field-label" for="RoundKey">RoundKey</label>
                <input id="RoundKey" class="input" type="text" name="RoundKey" value="<?= $text($roundKey) ?>" placeholder="035,212">
            </div>
            <div class="ca-toggle">
                <input id="HideRoundId" type="checkbox" name="HideRoundId" value="1" <?= $hideRoundId ? 'checked' : '' ?> style="width:18px;height:18px;">
                <label for="HideRoundId" style="font-weight:600">HideRoundId</label>
            </div>
            <div class="ca-toggle">
                <input id="HideTournament" type="checkbox" name="HideTournament" value="1" <?= $hideTournament ? 'checked' : '' ?> style="width:18px;height:18px;">
                <label for="HideTournament" style="font-weight:600">HideTournament</label>
            </div>
            <div class="ca-toggle">
                <input id="HideBadge" type="checkbox" name="HideBadge" value="1" <?= $hideBadge ? 'checked' : '' ?> style="width:18px;height:18px;">
                <label for="HideBadge" style="font-weight:600">HideBadge</label>
            </div>
        </form>
        <?php else: ?>
            <div class="ca-card"><p class="ca-help" style="margin:0">Önce vendorCode ve gameCode seçin.</p></div>
        <?php endif; ?>
    </div>

    <div class="ca-actions">
        <?php if ($hasScope): ?>
        <form class="ca-card" method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/agent-settings/pull')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <input type="hidden" name="vendor_code" value="<?= $text($vendorCode) ?>">
            <input type="hidden" name="game_code" value="<?= $text($gameCode) ?>">
            <input type="hidden" name="currency_code" value="<?= $text($currencyCode) ?>">
            <div style="font-weight:700;margin-bottom:8px">GetAgentSetting</div>
            <button class="btn btn--ghost" type="submit">API’den Çek</button>
        </form>
        <?php endif; ?>
        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:10px">Game Type</div>
            <?php foreach ($gameTypes as $code => $label): ?>
                <div class="ca-stat"><span><?= $text((string) $code) ?></span><strong><?= $text((string) $label) ?></strong></div>
            <?php endforeach; ?>
        </div>
        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:10px">Response Codes</div>
            <table class="ca-code-table">
                <thead><tr><th>Code</th><th>Message</th></tr></thead>
                <tbody>
                <?php foreach ($responseCodes as $code => $meta): ?>
                    <tr><td><?= (int) $code ?></td><td><?= $text((string) ($meta['msg'] ?? '')) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
