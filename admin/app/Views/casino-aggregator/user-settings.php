<?php

$userSettings = is_array($userSettings ?? null) ? $userSettings : [];
$userRow = is_array($userRow ?? null) ? $userRow : null;
$recentRows = is_array($recentRows ?? null) ? $recentRows : [];
$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash = trim((string) ($flash ?? ''));
$lookup = trim((string) ($lookup ?? ''));
$userCode = trim((string) ($userCode ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$isActive = !empty($configRow['is_active']) && $configRow['is_active'] !== '0';
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
    .ca-table th { color: var(--t-muted); font-weight:600; }
    .ca-search-row { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
    .ca-search-row .field { flex: 1; min-width: 180px; margin-bottom: 0; }
    @media (max-width: 900px) { .ca-grid { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · Casino Aggregator</span>
        <h1 class="hero-title">Kullanıcı <span class="accent">RTP</span></h1>
        <p class="hero-sub">Operator API Appendix 4 — UserSetting: üye bazlı LowRtp / HighRtp.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/casino-aggregator/agent-settings')) ?>">Agent Kontrolleri</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<?php if (!$isActive): ?>
    <div class="alert alert--warning" style="margin-bottom:16px">Aggregator entegrasyonu pasif. API push için önce ayarlardan aktif edin.</div>
<?php endif; ?>

<div class="ca-grid">
    <div style="display:flex;flex-direction:column;gap:18px;">
        <form class="ca-card" method="get" action="<?= $text(AdminAuth::url('/casino-aggregator/user-settings')) ?>">
            <div class="ca-section-title">Kullanıcı Ara</div>
            <div class="ca-search-row">
                <div class="field">
                    <label class="field-label" for="user">Kullanıcı ID veya kullanıcı adı</label>
                    <input id="user" class="input" type="text" name="user" value="<?= $text($lookup) ?>"
                           placeholder="örn. 42 veya player1" autocomplete="off">
                </div>
                <button class="btn btn--primary" type="submit">Getir</button>
            </div>
            <p class="ca-help">userCode olarak üye ID kullanılır (launch ile aynı).</p>
        </form>

        <?php if ($userRow !== null): ?>
            <form class="ca-card" method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/user-settings')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <input type="hidden" name="user_code" value="<?= $text($userCode) ?>">

                <div class="ca-section-title">
                    <?= $text((string) ($userRow['username'] ?? '')) ?>
                    <span style="font-weight:500;color:var(--t-muted);">#<?= (int) ($userRow['id'] ?? 0) ?></span>
                </div>
                <div class="ca-stat"><span>Bakiye</span><strong><?= $text(number_format((float) ($userRow['balance'] ?? 0), 2)) ?></strong></div>
                <div class="ca-stat" style="margin-bottom:14px">
                    <span>Durum</span>
                    <strong><?= !empty($userRow['banned']) ? '<span style="color:var(--danger,#c44)">Banned → BLOCK_USER</span>' : '<span style="color:var(--green)">Aktif</span>' ?></strong>
                </div>

                <div class="field">
                    <label class="field-label" for="LowRtp">LowRtp</label>
                    <input id="LowRtp" class="input" type="text" name="LowRtp" value="<?= $text($lowRtp) ?>"
                           placeholder="0.5" inputmode="decimal" autocomplete="off">
                </div>
                <div class="field">
                    <label class="field-label" for="HighRtp">HighRtp</label>
                    <input id="HighRtp" class="input" type="text" name="HighRtp" value="<?= $text($highRtp) ?>"
                           placeholder="0.8" inputmode="decimal" autocomplete="off">
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
                    <button class="btn btn--primary" type="submit">Kaydet + API</button>
                </div>
            </form>

            <form class="ca-card" method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/user-settings/pull')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <input type="hidden" name="user_code" value="<?= $text($userCode) ?>">
                <div style="font-weight:700;margin-bottom:8px">Uzaktan Çek</div>
                <p class="ca-help" style="margin-bottom:12px">GetUserSetting ile bu üyenin RTP değerlerini provider’dan alır.</p>
                <button class="btn btn--ghost" type="submit">API’den Çek</button>
            </form>
        <?php elseif ($lookup !== ''): ?>
            <div class="ca-card">
                <div class="alert alert--warning" style="margin:0">Kullanıcı bulunamadı: <?= $text($lookup) ?></div>
            </div>
        <?php endif; ?>

        <div class="ca-card">
            <div class="ca-section-title">Son Kullanıcı Ayarları</div>
            <?php if ($recentRows === []): ?>
                <p class="ca-help" style="margin:0">Henüz kayıt yok.</p>
            <?php else: ?>
                <table class="ca-table">
                    <thead>
                    <tr>
                        <th>User</th>
                        <th>Key</th>
                        <th>Value</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentRows as $row): ?>
                        <?php
                        $rowCode = (string) ($row['user_code'] ?? '');
                        $rowUser = trim((string) ($row['username'] ?? '')) !== ''
                            ? (string) $row['username']
                            : $rowCode;
                        ?>
                        <tr>
                            <td><?= $text($rowUser) ?> <span style="color:var(--t-muted)">#<?= $text($rowCode) ?></span></td>
                            <td><?= $text((string) ($row['setting_key'] ?? '')) ?></td>
                            <td><?= $text((string) ($row['setting_value'] ?? '')) ?></td>
                            <td><?= $text((string) ($row['updated_at'] ?? $row['synced_at'] ?? '')) ?></td>
                            <td>
                                <a class="btn btn--xs" href="<?= $text(AdminAuth::url('/casino-aggregator/user-settings?user=' . rawurlencode($rowCode))) ?>">Aç</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="ca-actions">
        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:10px">UserSetting Keys</div>
            <div class="ca-stat"><span>LowRtp</span><strong>0–1</strong></div>
            <div class="ca-stat"><span>HighRtp</span><strong>0–1</strong></div>
            <p class="ca-help" style="margin-top:10px">Üye ayarı agent ayarını override eder (provider önceliği).</p>
        </div>
        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:12px">Hızlı Linkler</div>
            <div class="ca-stat">
                <span>Agent Kontrolleri</span>
                <a class="btn btn--xs" href="<?= $text(AdminAuth::url('/casino-aggregator/agent-settings')) ?>">Aç</a>
            </div>
            <div class="ca-stat">
                <span>Aggregator Ayarları</span>
                <a class="btn btn--xs" href="<?= $text(AdminAuth::url('/casino-aggregator/settings')) ?>">Aç</a>
            </div>
        </div>
    </div>
</div>
