<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash     = trim((string) ($flash ?? ''));
$text      = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$siteEndpoint = trim((string) ($configRow['site_endpoint'] ?? getenv('DRAKON_SITE_ENDPOINT') ?: ''));
$webhookUrl   = $siteEndpoint !== '' ? rtrim($siteEndpoint, '/') . '/drakon_api' : '— site_endpoint ayarlanmamış —';
$isActive     = !empty($configRow['is_active']) && $configRow['is_active'] !== '0';
?>
<style>
    .drakon-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
    .drakon-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .drakon-actions { display: flex; flex-direction: column; gap: 10px; }
    .drakon-stat { display: flex; justify-content: space-between; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .drakon-stat:last-child { border-bottom: 0; }
    .drakon-secret { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .02em; }
    .drakon-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; margin-top: 6px; }
    .drakon-webhook-url { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; word-break: break-all; background: var(--bg); padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border); }
    @media (max-width: 900px) { .drakon-grid { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · Drakon</span>
        <h1 class="hero-title">Drakon <span class="accent">Casino</span></h1>
        <p class="hero-sub">Agent kimlik bilgileri, oyun senkronizasyonu ve webhook callback endpointi buradan yönetilir.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="drakonSettingsForm">Ayarları Kaydet</button>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<div class="drakon-grid">

    <!-- ─── Left: Settings form ──────────────────────────────────────────── -->
    <form id="drakonSettingsForm" class="drakon-card" method="post"
          action="<?= $text(AdminAuth::url('/drakon/settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="field">
            <label class="field-label" for="agent_code">Agent Code</label>
            <input id="agent_code" class="input" type="text" name="agent_code"
                   value="<?= $text($configRow['agent_code'] ?? '') ?>"
                   placeholder="Drakon agent code" autocomplete="off">
            <p class="drakon-help">Drakon yönetici panelinizden size verilen agent kodu.</p>
        </div>

        <div class="field">
            <label class="field-label" for="agent_token">Agent Token</label>
            <input id="agent_token" class="input drakon-secret" type="password" name="agent_token"
                   value=""
                   placeholder="<?= trim((string) ($configRow['agent_token'] ?? '')) !== '' ? 'Mevcut token korunacak' : 'Drakon agent token' ?>"
                   autocomplete="new-password">
            <p class="drakon-help">Hem API kimlik doğrulama hem de oyun başlatma için kullanılır. Boş bırakırsanız mevcut değer korunur.</p>
        </div>

        <div class="field">
            <label class="field-label" for="agent_secret">Agent Secret</label>
            <input id="agent_secret" class="input drakon-secret" type="password" name="agent_secret"
                   value=""
                   placeholder="<?= trim((string) ($configRow['agent_secret'] ?? '')) !== '' ? 'Mevcut secret korunacak' : 'Drakon agent secret' ?>"
                   autocomplete="new-password">
            <p class="drakon-help">Bearer token almak için <code>base64(agent_token:agent_secret)</code> şeklinde kullanılır.</p>
        </div>

        <div class="field">
            <label class="field-label" for="currency">Para Birimi</label>
            <input id="currency" class="input" type="text" name="currency"
                   value="<?= $text(strtoupper(trim((string) ($configRow['currency'] ?? 'USD')))) ?>"
                   placeholder="USD" maxlength="10" autocomplete="off">
            <p class="drakon-help">Drakon API'ye iletilen para birimi kodu. Örn: <code>TRY</code>, <code>USD</code>, <code>EUR</code>.</p>
        </div>

        <div class="field">
            <label class="field-label" for="site_endpoint">Site Endpoint (Webhook Base URL)</label>
            <input id="site_endpoint" class="input" type="url" name="site_endpoint"
                   value="<?= $text($siteEndpoint) ?>"
                   placeholder="https://hanky-cytoplasm-worshiper.ngrok-free.dev">
            <p class="drakon-help">
                Drakon yönetici paneline kayıt edeceğiniz webhook URL'nin kökü.
                Bu değere <code>/drakon_api</code> eklenerek webhook adresi oluşturulur.
            </p>
        </div>

        <div class="field">
            <label class="field-label" for="callback_secret">Callback Secret (Opsiyonel)</label>
            <input id="callback_secret" class="input drakon-secret" type="password" name="callback_secret"
                   value=""
                   placeholder="<?= trim((string) ($configRow['callback_secret'] ?? '')) !== '' ? 'Mevcut secret korunacak' : 'Webhook imzalama secret (opsiyonel)' ?>"
                   autocomplete="new-password">
            <p class="drakon-help">Drakon webhook isteklerini imzalamak için kullanılıyorsa buraya girin.</p>
        </div>

        <div class="field">
            <div style="display:flex;align-items:center;gap:10px;padding:12px 0;">
                <input id="is_active" type="checkbox" name="is_active" value="1"
                       <?= $isActive ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;">
                <label for="is_active" style="cursor:pointer;font-weight:600">
                    Drakon entegrasyonunu aktif et
                </label>
            </div>
        </div>
    </form>

    <!-- ─── Right: Info sidebar ──────────────────────────────────────────── -->
    <div class="drakon-actions">

        <div class="drakon-card">
            <div style="font-weight:700;margin-bottom:12px">📊 İstatistikler</div>
            <div class="drakon-stat">
                <span>Toplam Oyun</span>
                <strong><?= number_format((int) ($gamesCount ?? 0)) ?></strong>
            </div>
            <div class="drakon-stat">
                <span>Toplam İşlem</span>
                <strong><?= number_format((int) ($transactionsCount ?? 0)) ?></strong>
            </div>
            <div class="drakon-stat">
                <span>Durum</span>
                <strong><?= $isActive ? '<span style="color:var(--green)">Aktif</span>' : '<span style="color:var(--t-muted)">Pasif</span>' ?></strong>
            </div>
        </div>

        <div class="drakon-card">
            <div style="font-weight:700;margin-bottom:10px">🔗 Webhook URL</div>
            <p class="drakon-help" style="margin-bottom:8px">
                Drakon yönetici panelindeki agent ayarlarına bu adresi kaydedin:
            </p>
            <div class="drakon-webhook-url"><?= $text($webhookUrl) ?></div>
            <p class="drakon-help" style="margin-top:8px">
                ngrok aktifken ngrok URL'yi, üretimde domain URL'nizi kullanın.
            </p>
        </div>

        <div class="drakon-card">
            <div style="font-weight:700;margin-bottom:12px">⚡ Senkronizasyon</div>

            <form method="post" action="<?= $text(AdminAuth::url('/drakon/sync-providers')) ?>"
                  style="margin-bottom:10px">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <button class="btn btn--secondary" style="width:100%" type="submit">
                    Sağlayıcıları Sync Et
                </button>
            </form>

            <form method="post" action="<?= $text(AdminAuth::url('/drakon/sync-games')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <button class="btn btn--secondary" style="width:100%" type="submit">
                    Oyunları Sync Et
                </button>
            </form>
        </div>

        <div class="drakon-card">
            <div style="font-weight:700;margin-bottom:12px">🗂 Modüller</div>
            <?php
            $moduleLinks = [
                'drakon-providers'      => ['label' => 'Sağlayıcılar',   'path' => '/admin/tables/drakon_providers'],
                'drakon-games'          => ['label' => 'Oyun Kataloğu',  'path' => '/admin/tables/drakon_games'],
                'drakon-transactions'   => ['label' => 'İşlemler',       'path' => '/admin/tables/drakon_transactions'],
                'drakon-webhook-logs'   => ['label' => 'Webhook Logları','path' => '/admin/tables/drakon_webhook_logs'],
            ];
            foreach ($moduleLinks as $mKey => $mInfo):
                $mUrl = AdminAuth::url($mInfo['path']);
            ?>
            <div class="drakon-stat">
                <span><?= $text($mInfo['label']) ?></span>
                <a href="<?= $text($mUrl) ?>" class="btn btn--xs">Görüntüle</a>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>
