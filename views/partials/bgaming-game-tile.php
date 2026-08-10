<?php
/**
 * Casino game tile — casinomilyon622 CasinoGame markup.
 *
 * Expected: $game, $slotPlayTarget, $slotDemoHref, $slotFavoriteKind, $slotShowActionButtons
 * Optional: $slotTileImageOnly (lobby SSR style: image only; hover block still available unless true)
 */
$game = is_array($game ?? null) ? $game : [];
$tileModifier = isset($tileModifier) ? trim((string) $tileModifier) : '';
$imageOnly = !empty($slotTileImageOnly);
$playHref = $slotPlayTarget($game);
$demoHref = $slotDemoHref($game);
$playHrefJson = (string) json_encode($playHref, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
$runtimePlayIntentJs = 'if(event){event.preventDefault();event.stopPropagation();}if(window.__bgamingHandlePlayIntent){window.__bgamingHandlePlayIntent(event,' . $playHrefJson . ');}else{window.location.href=' . $playHrefJson . ';}';
$coverUrl = (string) ($game['cover'] ?? $game['image_url'] ?? '');
$coverFallbacks = is_array($game['cover_fallbacks'] ?? null) ? $game['cover_fallbacks'] : [];
if ($coverUrl === '' && $coverFallbacks !== []) {
    $coverUrl = (string) ($coverFallbacks[0] ?? '');
} elseif ($coverUrl !== '' && class_exists('CasinoAggregatorService', false)
    && method_exists('CasinoAggregatorService', 'preferCompatibleMediaUrl')) {
    $coverUrl = CasinoAggregatorService::preferCompatibleMediaUrl($coverUrl);
}
if (class_exists('CasinoAggregatorService', false)
    && method_exists('CasinoAggregatorService', 'expandFormatFallbacks')) {
    $seed = $coverFallbacks;
    if ($coverUrl !== '') {
        array_unshift($seed, $coverUrl);
    }
    $coverFallbacks = CasinoAggregatorService::expandFormatFallbacks($seed);
    if ($coverUrl === '' && $coverFallbacks !== []) {
        $coverUrl = (string) $coverFallbacks[0];
    }
}
$fallbackJson = $coverFallbacks !== []
    ? htmlspecialchars(json_encode(array_values($coverFallbacks), JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')
    : '';
$contentClass = 'casinoGameItemContent casinoGameItemContent--regular';
if ($tileModifier !== '') {
    $contentClass .= ' ' . $tileModifier;
}
$gameName = (string) ($game['game_name'] ?? '');
$providerName = (string) ($game['provider'] ?? $game['provider_name'] ?? '');
?>
<div class="<?= htmlspecialchars($contentClass, ENT_QUOTES, 'UTF-8') ?>"
     data-favorite-kind="<?= htmlspecialchars((string) ($slotFavoriteKind ?? 'bgaming'), ENT_QUOTES, 'UTF-8') ?>"
     data-catalog-id="<?= htmlspecialchars((string) ($game['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
     data-game-id="<?= htmlspecialchars((string) ($game['game_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
     onclick="<?= htmlspecialchars($runtimePlayIntentJs, ENT_QUOTES, 'UTF-8') ?>">
    <div class="casinoGameItem ">
        <img alt="<?= htmlspecialchars($gameName, ENT_QUOTES, 'UTF-8') ?>"
             loading="lazy"
             decoding="async"
             referrerpolicy="no-referrer"
             src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>"
             data-src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>"
             <?= $fallbackJson !== '' ? 'data-fallbacks="' . $fallbackJson . '" data-fallback-idx="0"' : '' ?>
             class="casinoGameItemImage casinoGameItemImage--regular"
             title="<?= htmlspecialchars($gameName, ENT_QUOTES, 'UTF-8') ?>"
             onload="window.__gameThumbLoaded&&window.__gameThumbLoaded(this)"
             onerror="window.__gameThumbError&&window.__gameThumbError(this)">
        <?php if (!$imageOnly): ?>
        <div class="casinoGameItemBlock">
            <div class="casinoGameIconsWrp">
                <div class="casinoGameIconsLeft"></div>
                <div class="casinoGameIconsFavoriteWrapper">
                    <i class="casinoGameItemFavBc bc-i-favorite " aria-hidden="true"></i>
                </div>
            </div>
            <div class="casinoGameItemLabelBc">
                <?= htmlspecialchars($gameName, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($providerName !== ''): ?>
                <span class="casinoGameItemProviderBc"><?= htmlspecialchars($providerName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($slotShowActionButtons)): ?>
            <div class="casinoGameButtons">
                <div class="casinoBtnWrp">
                    <a class="play-btn ds-btn ds-btn-variant--secondary ds-btn-size--sm ds-btn-radius--full ds-btn-appearance--filled"
                       href="<?= htmlspecialchars($playHref, ENT_QUOTES, 'UTF-8') ?>"
                       onclick="<?= htmlspecialchars($runtimePlayIntentJs, ENT_QUOTES, 'UTF-8') ?>">OYNA</a>
                </div>
                <?php if (!empty($game['has_demo'])): ?>
                <div class="casinoBtnWrp">
                    <a class="demo-btn ds-btn ds-btn-variant--transparent ds-btn-size--sm ds-btn-radius--full ds-btn-appearance--filled"
                       href="<?= htmlspecialchars($demoHref, ENT_QUOTES, 'UTF-8') ?>"
                       onclick="event.stopPropagation()">DEMO</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
