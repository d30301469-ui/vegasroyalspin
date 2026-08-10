<?php
/**
 * BGaming desktop lobby — dedicated copy of CM622 slot lobby markup.
 */
$lobbyGames = isset($lobbyGames) && is_array($lobbyGames) ? $lobbyGames : [];
$lobbyPopularGames = isset($lobbyPopularGames) && is_array($lobbyPopularGames) ? $lobbyPopularGames : [];
$lobbyHighWinsGames = isset($lobbyHighWinsGames) && is_array($lobbyHighWinsGames) ? $lobbyHighWinsGames : [];
$lobbyTournamentsGames = isset($lobbyTournamentsGames) && is_array($lobbyTournamentsGames) ? $lobbyTournamentsGames : [];
$lobbyMoreGames = isset($lobbyMoreGames) && is_array($lobbyMoreGames) ? $lobbyMoreGames : [];
$allUniqueProviders = isset($allUniqueProviders) && is_array($allUniqueProviders) ? $allUniqueProviders : [];
$lobbyHighWinsProviders = isset($lobbyHighWinsProviders) && is_array($lobbyHighWinsProviders) ? $lobbyHighWinsProviders : [];
$lobbyTournamentsProviders = isset($lobbyTournamentsProviders) && is_array($lobbyTournamentsProviders) ? $lobbyTournamentsProviders : [];
$totalSlots = (int) ($totalSlots ?? count($lobbyGames));
$lobbyPopularTotal = (int) ($lobbyPopularTotal ?? count($lobbyPopularGames));
$lobbyHighWinsTotal = (int) ($lobbyHighWinsTotal ?? count($lobbyHighWinsGames));
$lobbyTournamentsTotal = (int) ($lobbyTournamentsTotal ?? count($lobbyTournamentsGames));
$lobbyMoreTotal = (int) ($lobbyMoreTotal ?? ($totalSlots > 0 ? $totalSlots : count($lobbyMoreGames)));
$providerCount = count($allUniqueProviders);
$slotPageBaseUrl = isset($slotPageBaseUrl) ? (string) $slotPageBaseUrl : '/bgaming';
$providerFilterHref = static function (array $providers) use ($slotPageBaseUrl): string {
    if ($providers === []) {
        return $slotPageBaseUrl;
    }
    $tokens = array_map(
        static fn (string $name): string => rawurlencode((string) preg_replace('/\s+/', '-', trim($name))),
        $providers
    );
    return $slotPageBaseUrl . '?providers=' . implode(',', $tokens);
};
$lobbySectionTitles = isset($lobbySectionTitles) && is_array($lobbySectionTitles) ? $lobbySectionTitles : [];
$lobbySectionHrefs = isset($lobbySectionHrefs) && is_array($lobbySectionHrefs) ? $lobbySectionHrefs : [];
$lobbyPrimaryTitle = (string) ($lobbySectionTitles['primary'] ?? 'CASİNO OYUNLARI');
$lobbyPopularTitle = (string) ($lobbySectionTitles['popular'] ?? 'Casino Games');
$lobbyHighWinsTitle = (string) ($lobbySectionTitles['highWins'] ?? 'En Yüksek Kazançlar');
$lobbyTournamentsTitle = (string) ($lobbySectionTitles['tournaments'] ?? 'Turnuvalar');
$lobbyMoreTitle = (string) ($lobbySectionTitles['more'] ?? 'OYUNLAR');
$lobbyPrimaryHref = (string) ($lobbySectionHrefs['primary'] ?? ($slotPageBaseUrl . '?view=all'));
$lobbyPopularHref = (string) ($lobbySectionHrefs['popular'] ?? ($slotPageBaseUrl . '?sort=popular'));
$lobbyHighWinsHref = (string) ($lobbySectionHrefs['highWins'] ?? $providerFilterHref($lobbyHighWinsProviders));
$lobbyTournamentsHref = (string) ($lobbySectionHrefs['tournaments'] ?? $providerFilterHref($lobbyTournamentsProviders));
$lobbyMoreHref = (string) ($lobbySectionHrefs['more'] ?? ($slotPageBaseUrl . '?view=all'));

$sectionArrowPrev = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M12.9108 4.41083C13.2363 4.73626 13.2363 5.26377 12.9108 5.58921L8.50008 10L12.9108 14.4108C13.2363 14.7363 13.2363 15.2638 12.9108 15.5892C12.5854 15.9146 12.0579 15.9146 11.7325 15.5892L6.73248 10.5892C6.40704 10.2638 6.40704 9.73626 6.73248 9.41083L11.7325 4.41083C12.0579 4.08539 12.5854 4.08539 12.9108 4.41083Z"></path></svg>';
$sectionArrowNext = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7.08917 4.41083C7.41461 4.08539 7.94212 4.08539 8.26755 4.41083L13.2676 9.41083C13.593 9.73626 13.593 10.2638 13.2676 10.5892L8.26755 15.5892C7.94212 15.9146 7.41461 15.9146 7.08917 15.5892C6.76374 15.2638 6.76374 14.7363 7.08917 14.4108L11.4999 10L7.08917 5.58921C6.76374 5.26377 6.76374 4.73626 7.08917 4.41083Z"></path></svg>';

$renderSectionTitle = static function (
    string $title,
    string $countLabel,
    string $countHref,
    string $carouselId,
    bool $countAsButton = false
) use ($sectionArrowPrev, $sectionArrowNext): void {
    ?>
    <div class="sectionTitle">
        <h6 class="typography-heading typography-heading-6 sectionTitleText sectionTitleTextHeading"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h6>
        <?php if ($countLabel !== ''): ?>
            <?php if ($countAsButton): ?>
            <span class="ds-link ds-link-color--transparent ds-link-size--md" role="button" tabindex="0" data-href="<?= htmlspecialchars($countHref, ENT_QUOTES, 'UTF-8') ?>">
                <span class="ds-label ds-label--medium-regular link__label"><?= htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </span>
            <?php else: ?>
            <a class="ds-link ds-link-color--transparent ds-link-size--md" href="<?= htmlspecialchars($countHref, ENT_QUOTES, 'UTF-8') ?>">
                <span class="ds-label ds-label--medium-regular link__label"><?= htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <?php endif; ?>
        <?php endif; ?>
        <div class="sectionTitleBtnWrapper" data-lobby-carousel="<?= htmlspecialchars($carouselId, ENT_QUOTES, 'UTF-8') ?>">
            <button type="button" class="ds-btn ds-btn-variant--transparent ds-btn-size--sm ds-btn-radius--full ds-btn-appearance--filled ds-btn--icon lobby-carousel-prev" aria-label="Önceki" aria-disabled="true" disabled>
                <span class="CMSIconSVGWrapper btn__icon btn__icon--left"><?= $sectionArrowPrev ?></span>
            </button>
            <button type="button" class="ds-btn ds-btn-variant--transparent ds-btn-size--sm ds-btn-radius--full ds-btn-appearance--filled ds-btn--icon lobby-carousel-next" aria-label="Sonraki" aria-disabled="false">
                <span class="CMSIconSVGWrapper btn__icon btn__icon--left"><?= $sectionArrowNext ?></span>
            </button>
        </div>
    </div>
    <?php
};

$renderSkeletonSection = static function (string $title, string $carouselId, int $slides = 8) use ($renderSectionTitle): void {
    $renderSectionTitle($title, '', '', $carouselId);
    ?>
    <div class="carouselWrapper carousel carouselArrowsDisabled lobby-skeleton-carousel" data-lobby-carousel-el="<?= htmlspecialchars($carouselId, ENT_QUOTES, 'UTF-8') ?>">
        <div class="carousel swiper">
            <div class="swiper-wrapper">
                <?php for ($i = 0; $i < $slides; $i++): ?>
                <div class="swiper-slide">
                    <div class="skeleton-loader" style="display:block;width:100%;position:relative;border-radius:8px;aspect-ratio:44/31;"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <?php
};

$slotTileImageOnly = true;
?>
<div class="casinoLobbyContainer" id="casinoLobbySections">
    <div class="hm-row-bc" style="grid-template-columns: 12fr;">
        <div class="hm-row-bc-inner">
            <div class="casinoHorizontalGamesList casinoGamesWidget">
                <?php $renderSectionTitle($lobbyPrimaryTitle, '+' . $totalSlots, $lobbyPrimaryHref, 'lobby-casino-games'); ?>
                <div class="carouselWrapper carousel" data-lobby-carousel-el="lobby-casino-games">
                    <div class="swiper lobby-games-swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($lobbyGames as $game): ?>
                            <div class="swiper-slide">
                                <?php include VIEW_PATH . '/partials/bgaming-game-tile.php'; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="hm-row-bc" style="grid-template-columns: 12fr;">
        <div class="hm-row-bc-inner">
            <div class="casinoProvidersWidget">
                <?php $renderSectionTitle('Sağlayıcılar', '+' . $providerCount, '#providers', 'lobby-providers', true); ?>
                <div class="casinoProvidersWidgetCarousel">
                    <div class="carouselWrapper carousel" data-lobby-carousel-el="lobby-providers">
                        <div class="swiper lobby-providers-swiper">
                            <div class="swiper-wrapper">
                                <?php foreach ($allUniqueProviders as $provider): ?>
                                <div class="swiper-slide">
                                    <?= $renderProviderBtn($provider) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($lobbyPopularGames !== []): ?>
    <div class="hm-row-bc" style="grid-template-columns: 12fr;">
        <div class="hm-row-bc-inner">
            <div class="casinoHorizontalGamesList casinoGamesWidget">
                <?php $renderSectionTitle($lobbyPopularTitle, '+' . $lobbyPopularTotal, $lobbyPopularHref, 'lobby-popular'); ?>
                <div class="carouselWrapper carousel" data-lobby-carousel-el="lobby-popular">
                    <div class="swiper lobby-games-swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($lobbyPopularGames as $game): ?>
                            <div class="swiper-slide">
                                <?php include VIEW_PATH . '/partials/bgaming-game-tile.php'; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($lobbyHighWinsGames !== []): ?>
    <div class="hm-row-bc" style="grid-template-columns: 12fr;">
        <div class="hm-row-bc-inner">
            <div class="casinoHorizontalGamesList casinoGamesWidget">
                <?php $renderSectionTitle($lobbyHighWinsTitle, $lobbyHighWinsTotal > 0 ? '+' . $lobbyHighWinsTotal : '', $lobbyHighWinsHref, 'lobby-high-wins'); ?>
                <div class="carouselWrapper carousel" data-lobby-carousel-el="lobby-high-wins">
                    <div class="swiper lobby-games-swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($lobbyHighWinsGames as $game): ?>
                            <div class="swiper-slide">
                                <?php include VIEW_PATH . '/partials/bgaming-game-tile.php'; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($lobbyTournamentsGames !== []): ?>
    <div class="hm-row-bc" style="grid-template-columns: 12fr;">
        <div class="hm-row-bc-inner">
            <div class="casinoHorizontalGamesList casinoGamesWidget">
                <?php $renderSectionTitle($lobbyTournamentsTitle, $lobbyTournamentsTotal > 0 ? '+' . $lobbyTournamentsTotal : '', $lobbyTournamentsHref, 'lobby-tournaments'); ?>
                <div class="carouselWrapper carousel" data-lobby-carousel-el="lobby-tournaments">
                    <div class="swiper lobby-games-swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($lobbyTournamentsGames as $game): ?>
                            <div class="swiper-slide">
                                <?php include VIEW_PATH . '/partials/bgaming-game-tile.php'; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($lobbyMoreGames !== []): ?>
    <div class="hm-row-bc" style="grid-template-columns: 12fr;">
        <div class="hm-row-bc-inner">
            <div class="casinoHorizontalGamesList casinoGamesWidget">
                <?php $renderSectionTitle($lobbyMoreTitle, $lobbyMoreTotal > 0 ? '+' . $lobbyMoreTotal : '', $lobbyMoreHref, 'lobby-games-more'); ?>
                <div class="carouselWrapper carousel" data-lobby-carousel-el="lobby-games-more">
                    <div class="swiper lobby-games-swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($lobbyMoreGames as $game): ?>
                            <div class="swiper-slide">
                                <?php include VIEW_PATH . '/partials/bgaming-game-tile.php'; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
$slotTileImageOnly = false;
?>
