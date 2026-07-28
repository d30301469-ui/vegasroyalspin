<?php

$agentSettings = is_array($agentSettings ?? null) ? $agentSettings : [];
$configRow = is_array($configRow ?? null) ? $configRow : [];
$responseCodes = is_array($responseCodes ?? null) ? $responseCodes : [];
$gameTypes = is_array($gameTypes ?? null) ? $gameTypes : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$isActive = !empty($configRow['is_active']) && $configRow['is_active'] !== '0';
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
    .ca-code-table th { color: var(--t-muted); font-weight:600; }
    .ca-section-title { font-weight:700; margin: 18px 0 8px; }
    .ca-section-title:first-child { margin-top: 0; }
    @media (max-width: 900px) { .ca-grid { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · Casino Aggregator</span>
        <h1 class="hero-title">Agent <span class="accent">Kontrolleri</span></h1>
        <p class="hero-sub">Operator API Appendix 4 — AgentSetting: RTP aralığı, RoundKey ve görünürlük bayrakları.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/casino-aggregator/settings')) ?>">Aggregator Ayarları</a>
        <button class="btn btn--primary" type="submit" form="caAgentSettingsForm">Kaydet + API</button>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<?php if (!$isActive): ?>
    <div class="alert alert--warning" style="margin-bottom:16px">Aggregator entegrasyonu pasif. API push için önce ayarlardan aktif edin.</div>
<?php endif; ?>

<div class="ca-grid">
    <form id="caAgentSettingsForm" class="ca-card" method="post"
          action="<?= $text(AdminAuth::url('/casino-aggregator/agent-settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="ca-section-title">RTP (Agent)</div>
        <div class="field">
            <label class="field-label" for="LowRtp">LowRtp</label>
            <input id="LowRtp" class="input" type="text" name="LowRtp" value="<?= $text($lowRtp) ?>"
                   placeholder="0.5" inputmode="decimal" autocomplete="off">
            <p class="ca-help">Örnek: 0.5 — 0 ile 1 arası.</p>
        </div>
        <div class="field">
            <label class="field-label" for="HighRtp">HighRtp</label>
            <input id="HighRtp" class="input" type="text" name="HighRtp" value="<?= $text($highRtp) ?>"
                   placeholder="0.8" inputmode="decimal" autocomplete="off">
            <p class="ca-help">Örnek: 0.8 — LowRtp’den yüksek olmalıdır (provider doğrular).</p>
        </div>

        <div class="ca-section-title">RoundKey</div>
        <div class="field">
            <label class="field-label" for="RoundKey">RoundKey (vendor kodları)</label>
            <input id="RoundKey" class="input" type="text" name="RoundKey" value="<?= $text($roundKey) ?>"
                   placeholder="035,212" autocomplete="off">
            <p class="ca-help">Virgülle ayrılmış vendor kodları. Örnek: 035, 212</p>
        </div>

        <div class="ca-section-title">Görünürlük</div>
        <div class="ca-toggle">
            <input id="HideRoundId" type="checkbox" name="HideRoundId" value="1"
                   <?= $hideRoundId ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;">
            <label for="HideRoundId" style="cursor:pointer;font-weight:600">HideRoundId</label>
        </div>
        <div class="ca-toggle">
            <input id="HideTournament" type="checkbox" name="HideTournament" value="1"
                   <?= $hideTournament ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;">
            <label for="HideTournament" style="cursor:pointer;font-weight:600">HideTournament</label>
        </div>
        <div class="ca-toggle">
            <input id="HideBadge" type="checkbox" name="HideBadge" value="1"
                   <?= $hideBadge ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;">
            <label for="HideBadge" style="cursor:pointer;font-weight:600">HideBadge</label>
        </div>
    </form>

    <div class="ca-actions">
        <form class="ca-card" method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/agent-settings/pull')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <div style="font-weight:700;margin-bottom:8px">Uzaktan Çek</div>
            <p class="ca-help" style="margin-bottom:12px">GetAgentSetting ile provider’daki değerleri local mirror’a yazar.</p>
            <button class="btn btn--ghost" type="submit">API’den Çek</button>
        </form>

        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:10px">Game Type (Appendix 2)</div>
            <?php foreach ($gameTypes as $code => $label): ?>
                <div class="ca-stat"><span><?= $text((string) $code) ?></span><strong><?= $text((string) $label) ?></strong></div>
            <?php endforeach; ?>
        </div>

        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:10px">Response Codes (Appendix 1)</div>
            <table class="ca-code-table">
                <thead>
                <tr><th>Code</th><th>Message</th><th>Description</th></tr>
                </thead>
                <tbody>
                <?php foreach ($responseCodes as $code => $meta): ?>
                    <tr>
                        <td><?= (int) $code ?></td>
                        <td><?= $text((string) ($meta['msg'] ?? '')) ?></td>
                        <td><?= $text((string) ($meta['description'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:12px">Hızlı Linkler</div>
            <div class="ca-stat">
                <span>Kullanıcı RTP</span>
                <a class="btn btn--xs" href="<?= $text(AdminAuth::url('/casino-aggregator/user-settings')) ?>">Aç</a>
            </div>
            <div class="ca-stat">
                <span>Aggregator Ayarları</span>
                <a class="btn btn--xs" href="<?= $text(AdminAuth::url('/casino-aggregator/settings')) ?>">Aç</a>
            </div>
        </div>
    </div>
</div>
