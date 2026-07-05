<?php

declare(strict_types=1);

$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
$editCampaign = is_array($editCampaign ?? null) ? $editCampaign : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$campaign = static fn (string $field, mixed $default = ''): string => htmlspecialchars((string) ($editCampaign[$field] ?? $default), ENT_QUOTES, 'UTF-8');
$campaignDateTime = static function (string $field) use ($editCampaign): string {
    $raw = (string) ($editCampaign[$field] ?? '');
    if ($raw === '') {
        return '';
    }

    if (is_numeric($raw)) {
        $ts = (int) $raw;
    } else {
        $parsed = strtotime($raw);
        if ($parsed === false) {
            return '';
        }
        $ts = $parsed;
    }

    return date('Y-m-d\\TH:i', $ts);
};
$isEdit = $editCampaign !== [];
?>
<style>
    .bgaming-campaign-grid { display:grid; grid-template-columns:1fr; gap:18px; align-items:start; }
    .bgaming-campaign-card { background: var(--bg-card); border:1px solid var(--border); border-radius:18px; box-shadow: var(--shadow-card); }
    .bgaming-campaign-head { padding:18px 20px; border-bottom:1px solid var(--border); }
    .bgaming-campaign-body { padding:18px 20px; }
    .bgaming-campaign-table { width:100%; border-collapse:collapse; }
    .bgaming-campaign-table th, .bgaming-campaign-table td { padding:12px 10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .bgaming-campaign-table th { color: var(--t-muted); font-size:12px; text-transform:uppercase; letter-spacing:.06em; }
    .bgaming-meta { color: var(--t-muted); font-size:13px; }
    .bgaming-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; }
    .bgaming-inline-form { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .bgaming-stack { display:flex; flex-direction:column; gap:18px; }
    @media (max-width: 1080px) { .bgaming-campaign-grid, .bgaming-inline-form { grid-template-columns:1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · BGaming</span>
        <h1 class="hero-title">BGaming <span class="accent">Kampanyaları</span></h1>
        <p class="hero-sub">Admin panelden freespin ve promo kampanyası tanımlayın, ardından kullanıcıya atayın.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">BGaming Ayarları</a>
        <a class="btn btn--secondary" href="<?= $text(AdminAuth::url('/bgaming/campaigns/assignments')) ?>">Kampanya Ata</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<div class="bgaming-campaign-grid">
    <div class="bgaming-stack">
        <section class="bgaming-campaign-card">
            <div class="bgaming-campaign-head">
                <h2 style="margin:0;font-size:18px"><?= $isEdit ? 'Kampanya Düzenle' : 'Yeni Kampanya' ?></h2>
            </div>
            <div class="bgaming-campaign-body">
                <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/store')) ?>">
                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $campaign('id') ?>"><?php endif; ?>
                    <div class="bgaming-inline-form">
                        <div class="field">
                            <label class="field-label" for="campaign_title">Başlık</label>
                            <input id="campaign_title" class="input" type="text" name="title" required maxlength="190" value="<?= $campaign('title') ?>">
                        </div>
                        <div class="field">
                            <label class="field-label" for="campaign_code">Campaign Code</label>
                            <input id="campaign_code" class="input bgaming-code" type="text" name="campaign_code" maxlength="190" value="<?= $campaign('campaign_code') ?>" placeholder="Boş bırakılırsa otomatik oluşur">
                        </div>
                        <div class="field">
                            <label class="field-label" for="campaign_type">Tip</label>
                            <select id="campaign_type" class="input" name="campaign_type">
                                <option value="freespin"<?= $campaign('campaign_type', 'freespin') === 'freespin' ? ' selected' : '' ?>>Freespin</option>
                                <option value="promo"<?= $campaign('campaign_type') === 'promo' ? ' selected' : '' ?>>Promo</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="game_identifier">Game Identifier</label>
                            <input id="game_identifier" class="input" type="text" name="game_identifier" maxlength="120" value="<?= $campaign('game_identifier') ?>" placeholder="acceptance:test veya gerçek oyun kodu">
                        </div>
                        <div class="field">
                            <label class="field-label" for="currency_code">Para Birimi</label>
                            <input id="currency_code" class="input" type="text" name="currency_code" maxlength="8" value="<?= $campaign('currency_code', $configRow['currency'] ?? 'TRY') ?>">
                        </div>
                        <div class="field">
                            <label class="field-label" for="freespins_per_player">Kişi Başı Freespin</label>
                            <input id="freespins_per_player" class="input" type="number" min="0" name="freespins_per_player" value="<?= $campaign('freespins_per_player', '0') ?>">
                        </div>
                        <div class="field">
                            <label class="field-label" for="promo_amount">Promo Tutarı</label>
                            <input id="promo_amount" class="input" type="number" min="0" step="0.01" name="promo_amount" value="<?= $campaign('promo_amount', '0.00') ?>">
                        </div>
                        <div class="field">
                            <label class="field-label" for="wagering_multiplier">Çevrim Çarpanı</label>
                            <input id="wagering_multiplier" class="input" type="number" min="0" step="0.10" name="wagering_multiplier" value="<?= $campaign('wagering_multiplier', '0') ?>">
                        </div>
                        <div class="field">
                            <label class="field-label" for="begins_at">Başlangıç</label>
                            <input id="begins_at" class="input" type="datetime-local" name="begins_at" value="<?= $text($campaignDateTime('begins_at')) ?>">
                        </div>
                        <div class="field">
                            <label class="field-label" for="expires_at">Bitiş</label>
                            <input id="expires_at" class="input" type="datetime-local" name="expires_at" value="<?= $text($campaignDateTime('expires_at')) ?>">
                        </div>
                        <div class="field" style="grid-column:1/-1">
                            <label class="switch" style="margin-top:8px">
                                <input type="checkbox" name="active" value="1" <?= ((int) ($editCampaign['active'] ?? 1)) === 1 ? 'checked' : '' ?>>
                                <span class="track"></span>
                                Kampanya aktif
                            </label>
                        </div>
                        <div class="field" style="grid-column:1/-1">
                            <label class="field-label" for="campaign_notes">Notlar</label>
                            <textarea id="campaign_notes" class="input" name="notes" rows="3" style="resize:vertical"><?= $campaign('notes') ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top:16px">
                        <?php if ($isEdit): ?><a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Yeni Form</a><?php endif; ?>
                        <button class="btn btn--primary" type="submit"><?= $isEdit ? 'Kampanyayı Güncelle' : 'Kampanyayı Kaydet' ?></button>
                    </div>
                </form>
            </div>
        </section>

        <section class="bgaming-campaign-card">
            <div class="bgaming-campaign-head">
                <h2 style="margin:0;font-size:18px">Mevcut Kampanyalar</h2>
            </div>
            <div class="bgaming-campaign-body" style="padding-top:4px">
                <table class="bgaming-campaign-table">
                    <thead>
                        <tr>
                            <th>Kampanya</th>
                            <th>Tip</th>
                            <th>Değer</th>
                            <th>Durum</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($campaigns === []): ?>
                            <tr><td colspan="5" class="bgaming-meta">Henüz BGaming kampanyası tanımlanmadı.</td></tr>
                        <?php else: ?>
                            <?php foreach ($campaigns as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= $text($row['title'] ?? '') ?></strong><br>
                                        <span class="bgaming-code"><?= $text($row['campaign_code'] ?? '') ?></span>
                                        <?php if (!empty($row['game_identifier'])): ?><div class="bgaming-meta">Oyun: <?= $text($row['game_identifier']) ?></div><?php endif; ?>
                                    </td>
                                    <td><?= $text(strtoupper((string) ($row['campaign_type'] ?? ''))) ?></td>
                                    <td>
                                        <?php if (($row['campaign_type'] ?? '') === 'promo'): ?>
                                            <?= $text(number_format((float) ($row['promo_amount'] ?? 0), 2, ',', '.')) ?>
                                        <?php else: ?>
                                            <?= $text((string) ($row['freespins_per_player'] ?? '0')) ?> spin
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge dot <?= ((int) ($row['active'] ?? 0)) === 1 ? 'success' : 'danger' ?>"><?= ((int) ($row['active'] ?? 0)) === 1 ? 'Aktif' : 'Pasif' ?></span>
                                    </td>
                                    <td><a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/campaigns?id=' . (int) ($row['id'] ?? 0))) ?>">Düzenle</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

</div>
