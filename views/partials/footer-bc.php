<?php
require __DIR__ . '/footer-bc-data.php';

$footerCopyrightYear = (int) date('Y');
$footerCopyrightText = ($footerCopyrightSince === $footerCopyrightYear)
    ? $footerCopyrightYear . ' ' . $footerSiteName
    : $footerCopyrightSince . ' - ' . $footerCopyrightYear . ' ' . $footerSiteName;
?>
<div class="layout-footer-holder-bc">
    <footer class="footer-bc">
        <div class="footerWrapper">
            <?php if (!empty($footerShowCustomContent)): ?>
                <?php require __DIR__ . '/footer-bc-about.php'; ?>
            <?php endif; ?>
            <div class="footerContainerWrapper">
                <div class="footerContainer">
                    <div class="footerHeader">
                        <div class="footerInnerLeftCol">
                            <ul class="footerSocialLinks">
                                <?php foreach ($footerSocialIcons as $icon): ?>
                                    <?php
                                    if (!is_array($icon)) {
                                        continue;
                                    }
                                    $network = (string) ($icon['network'] ?? '');
                                    if ($network === '') {
                                        continue;
                                    }
                                    $url = (string) ($icon['url'] ?? 'javascript:void(0)');
                                    ?>
                                    <li class="footerSocialLink footerSocialLink--<?= htmlspecialchars($network) ?>">
                                        <a href="<?= htmlspecialchars($url) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           aria-label="<?= htmlspecialchars(ucfirst($network)) ?>">
                                            <i class="bc-i-<?= htmlspecialchars($network) ?>"></i>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="footerInfoColumn">
                            <div class="footerClockWidget" id="footerClockWidget" aria-live="polite">00:00:00</div>
                            <div class="footerLanguageDropdown languageDropdown">
                                <button type="button" class="footerLanguageTrigger" aria-haspopup="listbox" aria-expanded="false">
                                    <img src="<?= htmlspecialchars($footerFlagImage) ?>" alt="Türkiye" width="20" height="14" class="footerLanguageFlag">
                                    <span class="footerLanguageCode">TUR</span>
                                    <i class="bc-i-small-arrow-right footerLanguageChevron" aria-hidden="true"></i>
                                </button>
                                <ul class="footerLanguageMenu" role="listbox" hidden>
                                    <li>
                                        <a class="footerLanguageOption is-active" role="option" aria-selected="true" data-lang="tr" href="?lang=tr">
                                            <span class="flag-icon flag-icon-tr" aria-hidden="true"></span>
                                            <span class="code">TUR</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="footerLanguageOption" role="option" aria-selected="false" data-lang="en" href="?lang=en">
                                            <span class="flag-icon flag-icon-us" aria-hidden="true"></span>
                                            <span class="code">ENG</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="footerLanguageOption" role="option" aria-selected="false" data-lang="de" href="?lang=de">
                                            <span class="flag-icon flag-icon-de" aria-hidden="true"></span>
                                            <span class="code">DEU</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="footerLinksSection">
                        <div class="footerLinkCols">
                            <?php foreach ($footerMenuColumns as $column): ?>
                                <?php if (!is_array($column)) { continue; } ?>
                                <?php $columnLinks = is_array($column['links'] ?? null) ? $column['links'] : []; ?>
                                <div class="footerLinkCol">
                                    <h3 class="footerLinkColTitle">
                                        <?php if (!empty($column['icon'])): ?>
                                            <i class="bc-i-<?= htmlspecialchars($column['icon']) ?> footerLinkColTitleIcon bc-i-footer-icon-holder" aria-hidden="true"></i>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars((string) ($column['title'] ?? '')) ?></span>
                                    </h3>
                                    <ul class="footerLinkColLinks">
                                        <?php foreach ($columnLinks as $link): ?>
                                            <?php
                                            if (!is_array($link)) {
                                                continue;
                                            }
                                            $linkHref = (string) ($link['href'] ?? 'javascript:void(0)');
                                            if ($linkHref === '' || str_starts_with($linkHref, 'javascript:')) {
                                                $linkHref = ApiFooterPages::hrefForTitle((string) ($link['title'] ?? ''));
                                            }
                                            $linkTarget = (string) ($link['target'] ?? '_self');
                                            ?>
                                            <li class="footerLinkColEl">
                                                <a href="<?= htmlspecialchars($linkHref) ?>"
                                                   target="<?= htmlspecialchars($linkTarget) ?>"
                                                   <?php if ($linkTarget === '_blank'): ?>rel="noopener noreferrer"<?php endif; ?>>
                                                    <?php if (!empty($link['icon'])): ?>
                                                        <i class="bc-i-<?= htmlspecialchars($link['icon']) ?> footerLinkIcon" aria-hidden="true"></i>
                                                    <?php endif; ?>
                                                    <span><?= htmlspecialchars((string) ($link['title'] ?? '')) ?></span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="sliderGroup">
                        <div class="sliderContent">
                            <h4 class="sliderTitle">YÖNETMELİKLER &amp; ORTAKLAR</h4>
                            <?php foreach ($footerLicenceRows as $row): ?>
                                <div class="ftr-partners-row-bc">
                                    <div class="ftr-partners-row-inner-bc">
                                        <?php foreach ((is_array($row) ? $row : []) as $item): ?>
                                            <?php if (!is_array($item)) { continue; } ?>
                                            <?php $itemType = (string) ($item['type'] ?? ''); ?>
                                            <?php if ($itemType === 'iframe'): ?>
                                                <div class="ftr-partners-licence-iframe">
                                                    <iframe src="<?= htmlspecialchars((string) ($item['src'] ?? '')) ?>"
                                                            title="Lisans doğrulama"
                                                            loading="lazy"
                                                            scrolling="no"
                                                            frameborder="0"
                                                            referrerpolicy="no-referrer"
                                                            sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
                                                </div>
                                            <?php elseif ($itemType === 'image'): ?>
                                                <a href="<?= htmlspecialchars((string) ($item['href'] ?? '#')) ?>"
                                                   target="_blank"
                                                   rel="noopener noreferrer"
                                                   class="ftr-partners-r-img">
                                                    <img src="<?= htmlspecialchars((string) ($item['src'] ?? '')) ?>"
                                                         alt=""
                                                         loading="lazy">
                                                </a>
                                            <?php elseif ($itemType === 'text'): ?>
                                                <div class="ftr-copy-rights-bc"><?= (string) ($item['html'] ?? '') ?></div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="sliderContent">
                            <h4 class="sliderTitle">ÖDEMELER</h4>
                            <div class="horizontalSliderWrapper horizontalItemsExpanded alignedCenter"
                                 data-footer-slider>
                                <i class="horizontalSliderNav bc-i-small-arrow-left" data-slider-prev aria-hidden="true"></i>
                                <div class="horizontalSliderViewport">
                                    <div class="horizontalSliderRow">
                                        <?php foreach ($footerPayments as $payment): ?>
                                            <?php if (!is_array($payment)) { continue; } ?>
                                            <?php $paymentImage = (string) ($payment['image'] ?? ''); ?>
                                            <?php if ($paymentImage === '') { continue; } ?>
                                            <div class="horizontalSliderElem footerSliderImage">
                                                <img class="horizontalSliderImg payment-logo"
                                                     src="<?= htmlspecialchars($paymentImage) ?>"
                                                     alt="<?= htmlspecialchars((string) ($payment['name'] ?? '')) ?>"
                                                     loading="lazy">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <i class="horizontalSliderNav bc-i-small-arrow-right" data-slider-next aria-hidden="true"></i>
                            </div>
                        </div>
                    </div>

                    <div class="copyRightBlock">
                        <p class="footerCopyrights"><?= htmlspecialchars($footerCopyrightText) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</div>
