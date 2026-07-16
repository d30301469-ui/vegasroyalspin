<?php

$configRow   = is_array($configRow ?? null) ? $configRow : [];
$vendors     = is_array($vendors ?? null) ? $vendors : [];
$limits      = is_array($limits ?? null) ? $limits : [];
$campaigns   = is_array($campaigns ?? null) ? $campaigns : [];
$vendorError = trim((string) ($vendorError ?? ''));
$limitsError = trim((string) ($limitsError ?? ''));
$limitVendor = trim((string) ($limitVendor ?? ''));
$limitGames  = trim((string) ($limitGames ?? ''));
$flash       = trim((string) ($flash ?? ''));

$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$fmtTs = static function (mixed $ts): string {
    $ts = (int) $ts;
    return $ts > 0 ? date('d.m.Y H:i', $ts) : '—';
};
?>
<style>
    .drk-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 18px; align-items: start; }
    .drk-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .drk-card + .drk-card { margin-top: 18px; }
    .drk-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; margin-top: 6px; }
    .drk-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .drk-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .drk-table th, .drk-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .drk-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .drk-badge--on { background: rgba(51,193,107,.15); color: #33c16b; }
    .drk-badge--off { background: rgba(255,99,115,.15); color: #ff6373; }
    .drk-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .drk-chip { background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 4px 8px; font-size: 12px; }
    .drk-inline { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    @media (max-width: 1000px) { .drk-grid { grid-template-columns: 1fr; } .drk-row { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · Drakon</span>
        <h1 class="hero-title">Freespin / <span class="accent">Kampanya</span></h1>
        <p class="hero-sub">Drakon Campaign API üzerinden freespin kampanyaları oluşturun, oyuncu atayın ve yönetin.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="drkCreateForm">Kampanya Oluştur</button>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>
<?php if ($vendorError !== ''): ?>
    <div class="alert alert--warning" style="margin-bottom:16px">Sağlayıcı listesi alınamadı: <?= $text($vendorError) ?></div>
<?php endif; ?>

<div class="drk-grid">

    <!-- ─── Left: Create campaign ────────────────────────────────────────── -->
    <div>
        <form id="drkCreateForm" class="drk-card" method="post"
              action="<?= $text(AdminAuth::url('/drakon/campaigns/create')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <div style="font-weight:700;margin-bottom:14px">🎁 Yeni Freespin Kampanyası</div>

            <div class="drk-row">
                <div class="field">
                    <label class="field-label" for="campaign_code">Kampanya Kodu</label>
                    <input id="campaign_code" class="input" type="text" name="campaign_code"
                           placeholder="cmp_try_0001" autocomplete="off" required>
                </div>
                <div class="field">
                    <label class="field-label" for="vendor">Sağlayıcı</label>
                    <?php if ($vendors !== []): ?>
                        <select id="vendor" class="input" name="vendor" required>
                            <option value="">Seçiniz…</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?= $text($vendor) ?>"><?= $text($vendor) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input id="vendor" class="input" type="text" name="vendor" placeholder="pragmatic" required>
                    <?php endif; ?>
                </div>
            </div>

            <div class="drk-row">
                <div class="field">
                    <label class="field-label" for="game_id">Oyun ID</label>
                    <input id="game_id" class="input" type="text" name="game_id" placeholder="17000" autocomplete="off" required>
                </div>
                <div class="field">
                    <label class="field-label" for="total_bet">Total Bet</label>
                    <input id="total_bet" class="input" type="text" name="total_bet" placeholder="0.50" autocomplete="off" required>
                    <p class="drk-help">Limit endpoint'inden dönen izinli bir değeri kullanın.</p>
                </div>
            </div>

            <div class="drk-row">
                <div class="field">
                    <label class="field-label" for="freespins_per_player">Oyuncu Başına Freespin</label>
                    <input id="freespins_per_player" class="input" type="number" min="1" name="freespins_per_player" placeholder="10" required>
                </div>
                <div class="field">
                    <label class="field-label" for="players">Oyuncular (opsiyonel)</label>
                    <input id="players" class="input" type="text" name="players" placeholder="12345, 56789" autocomplete="off">
                    <p class="drk-help">Virgül/boşluk ile ayrılmış user_id listesi.</p>
                </div>
            </div>

            <div class="drk-row">
                <div class="field">
                    <label class="field-label" for="begins_at">Başlangıç</label>
                    <input id="begins_at" class="input" type="datetime-local" name="begins_at" required>
                </div>
                <div class="field">
                    <label class="field-label" for="expires_at">Bitiş</label>
                    <input id="expires_at" class="input" type="datetime-local" name="expires_at" required>
                </div>
            </div>
        </form>

        <!-- Local campaigns -->
        <div class="drk-card">
            <div style="font-weight:700;margin-bottom:14px">📋 Kayıtlı Kampanyalar</div>
            <?php if ($campaigns === []): ?>
                <p class="drk-help">Henüz kampanya oluşturulmadı.</p>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="drk-table">
                        <thead>
                            <tr>
                                <th>Kod</th>
                                <th>Sağlayıcı</th>
                                <th>Freespin</th>
                                <th>Total Bet</th>
                                <th>Oyuncu</th>
                                <th>Tarih</th>
                                <th>Durum</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign): ?>
                                <?php
                                $code   = (string) ($campaign['campaign_code'] ?? '');
                                $active = !empty($campaign['active']) && (string) ($campaign['status'] ?? '') !== 'canceled';
                                ?>
                                <tr>
                                    <td><strong><?= $text($code) ?></strong></td>
                                    <td><?= $text($campaign['vendor'] ?? '') ?></td>
                                    <td><?= (int) ($campaign['freespins_per_player'] ?? 0) ?></td>
                                    <td><?= $text($campaign['total_bet'] ?? '') ?></td>
                                    <td><?= (int) ($campaign['player_count'] ?? 0) ?></td>
                                    <td><?= $text($fmtTs($campaign['begins_at'] ?? 0)) ?><br><span class="drk-help"><?= $text($fmtTs($campaign['expires_at'] ?? 0)) ?></span></td>
                                    <td>
                                        <?php if ($active): ?>
                                            <span class="drk-badge drk-badge--on">aktif</span>
                                        <?php else: ?>
                                            <span class="drk-badge drk-badge--off"><?= $text($campaign['status'] ?? 'pasif') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($active): ?>
                                            <div class="drk-inline">
                                                <form method="post" action="<?= $text(AdminAuth::url('/drakon/campaigns/players/add')) ?>" class="drk-inline">
                                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                                    <input type="hidden" name="campaign_code" value="<?= $text($code) ?>">
                                                    <input class="input" style="max-width:130px;padding:4px 8px" type="text" name="players" placeholder="user_id ekle">
                                                    <button class="btn btn--sm" type="submit">Ekle</button>
                                                </form>
                                                <form method="post" action="<?= $text(AdminAuth::url('/drakon/campaigns/players/remove')) ?>" class="drk-inline">
                                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                                    <input type="hidden" name="campaign_code" value="<?= $text($code) ?>">
                                                    <input class="input" style="max-width:130px;padding:4px 8px" type="text" name="players" placeholder="user_id çıkar">
                                                    <button class="btn btn--sm" type="submit">Çıkar</button>
                                                </form>
                                                <form method="post" action="<?= $text(AdminAuth::url('/drakon/campaigns/cancel')) ?>"
                                                      onsubmit="return confirm('Kampanya iptal edilsin mi?');">
                                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                                    <input type="hidden" name="campaign_code" value="<?= $text($code) ?>">
                                                    <button class="btn btn--sm btn--danger" type="submit">İptal</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="drk-help">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── Right: Vendor limits helper ──────────────────────────────────── -->
    <div>
        <form class="drk-card" method="get" action="<?= $text(AdminAuth::url('/drakon/campaigns')) ?>">
            <div style="font-weight:700;margin-bottom:12px">🔎 Sağlayıcı Limitleri</div>
            <div class="field">
                <label class="field-label" for="limit_vendor">Sağlayıcı</label>
                <input id="limit_vendor" class="input" type="text" name="vendor" value="<?= $text($limitVendor) ?>" placeholder="pragmatic">
            </div>
            <div class="field">
                <label class="field-label" for="limit_games">Oyunlar (opsiyonel)</label>
                <input id="limit_games" class="input" type="text" name="games" value="<?= $text($limitGames) ?>" placeholder="17000,17003">
                <p class="drk-help">Virgülle ayrılmış game_id listesi.</p>
            </div>
            <button class="btn btn--secondary" type="submit" style="width:100%">Limitleri Getir</button>
        </form>

        <div class="drk-card">
            <div style="font-weight:700;margin-bottom:12px">📐 İzinli Limitler</div>
            <?php if ($limitsError !== ''): ?>
                <div class="alert alert--warning"><?= $text($limitsError) ?></div>
            <?php elseif ($limits === []): ?>
                <p class="drk-help">Bir sağlayıcı seçip "Limitleri Getir" ile izinli total_bet ve freespin değerlerini görüntüleyin.</p>
            <?php else: ?>
                <?php foreach ($limits as $limit): ?>
                    <div style="padding:10px 0;border-bottom:1px solid var(--border)">
                        <div style="font-weight:600">
                            <?= $text($limit['vendor'] ?? '') ?> · Oyun <?= $text($limit['game_id'] ?? '') ?>
                            <span class="drk-help">(<?= $text($limit['currency_code'] ?? '') ?>)</span>
                        </div>
                        <div class="drk-help">total_bet:</div>
                        <div class="drk-chips">
                            <?php foreach ((array) ($limit['limits'] ?? []) as $bet): ?>
                                <span class="drk-chip"><?= $text($bet) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="drk-help" style="margin-top:6px">freespins_per_player:</div>
                        <div class="drk-chips">
                            <?php foreach ((array) ($limit['freespins_per_player'] ?? []) as $fs): ?>
                                <span class="drk-chip"><?= $text($fs) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="drk-card">
            <div style="font-weight:700;margin-bottom:12px">ℹ️ Sağlayıcılar</div>
            <?php if ($vendors === []): ?>
                <p class="drk-help">Sağlayıcı listesi boş. Drakon ayarlarının aktif ve kimlik bilgilerinin doğru olduğundan emin olun.</p>
            <?php else: ?>
                <div class="drk-chips">
                    <?php foreach ($vendors as $vendor): ?>
                        <span class="drk-chip"><?= $text($vendor) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
