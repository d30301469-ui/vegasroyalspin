<?php
require VIEW_PATH . '/partials/footer-bc-data.php';

$footerCopyrightYear = (int) date('Y');
$footerCopyrightText = ($footerCopyrightSince === $footerCopyrightYear)
    ? $footerCopyrightYear . ' ' . $footerSiteName
    : $footerCopyrightSince . ' - ' . $footerCopyrightYear . ' ' . $footerSiteName;

$mobileFooterLinkHref = static function (array $link): string {
    $href = (string) ($link['href'] ?? '#');
    if ($href === '' || str_starts_with($href, 'javascript:')) {
        $href = ApiFooterPages::hrefForTitle((string) ($link['title'] ?? ''));
    }
    return $href;
};
?>
<footer class="mobile-footer-bc" aria-label="Site footer">
  <div class="mobile-footer-bc__top">
    <ul class="mobile-footer-bc__socials" aria-label="Sosyal medya">
      <?php foreach ($footerSocialIcons as $icon): ?>
        <?php
        if (!is_array($icon)) {
            continue;
        }
        $network = trim((string) ($icon['network'] ?? ''));
        $url = (string) ($icon['url'] ?? '#');
        if ($network === '') {
            continue;
        }
        ?>
        <li class="mobile-footer-bc__social-item mobile-footer-bc__social-item--<?= htmlspecialchars($network, ENT_QUOTES, 'UTF-8') ?>">
          <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
             target="_blank"
             rel="noopener noreferrer"
             aria-label="<?= htmlspecialchars(ucfirst($network), ENT_QUOTES, 'UTF-8') ?>">
            <i class="bc-i-<?= htmlspecialchars($network, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="mobile-footer-bc__meta">
      <div class="mobile-footer-bc__clock" id="footerClockWidget" aria-live="polite">00:00:00</div>
      <button type="button" class="mobile-footer-bc__language" aria-label="Dil: Türkçe">
        <img src="<?= htmlspecialchars($footerFlagImage, ENT_QUOTES, 'UTF-8') ?>" alt="" width="20" height="14">
        <span>TUR</span>
        <i class="bc-i-small-arrow-right" aria-hidden="true"></i>
      </button>
    </div>
  </div>

  <?php if (!empty($footerMenuColumns)): ?>
  <div class="mobile-footer-bc__links">
    <?php foreach ($footerMenuColumns as $index => $column): ?>
      <?php
      if (!is_array($column)) {
          continue;
      }
      $links = is_array($column['links'] ?? null) ? $column['links'] : [];
      if (!$links) {
          continue;
      }
      ?>
      <details class="mobile-footer-bc__group" <?= $index === 0 ? 'open' : '' ?>>
        <summary class="mobile-footer-bc__group-title">
          <?php if (!empty($column['icon'])): ?>
            <i class="bc-i-<?= htmlspecialchars((string) $column['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
          <?php endif; ?>
          <span><?= htmlspecialchars((string) ($column['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </summary>
        <ul class="mobile-footer-bc__group-list">
          <?php foreach ($links as $link): ?>
            <?php
            if (!is_array($link)) {
                continue;
            }
            $href = $mobileFooterLinkHref($link);
            $target = (string) ($link['target'] ?? '_self');
            ?>
            <li>
              <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                 target="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>"
                 <?= $target === '_blank' ? 'rel="noopener noreferrer"' : '' ?>>
                <?php if (!empty($link['icon'])): ?>
                  <i class="bc-i-<?= htmlspecialchars((string) $link['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                <?php endif; ?>
                <span><?= htmlspecialchars((string) ($link['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($footerLicenceRows)): ?>
  <section class="mobile-footer-bc__section">
    <h3>YÖNETMELİKLER &amp; ORTAKLAR</h3>
    <div class="mobile-footer-bc__partners">
      <?php foreach ($footerLicenceRows as $row): ?>
        <?php foreach ((is_array($row) ? $row : []) as $item): ?>
          <?php
          if (!is_array($item)) {
              continue;
          }
          $itemType = (string) ($item['type'] ?? '');
          ?>
          <?php if ($itemType === 'iframe'): ?>
            <span class="mobile-footer-bc__partner mobile-footer-bc__partner--iframe">
              <iframe src="<?= htmlspecialchars((string) ($item['src'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      title="Lisans doğrulama"
                      loading="lazy"
                      scrolling="no"
                      frameborder="0"></iframe>
            </span>
          <?php elseif ($itemType === 'image'): ?>
            <a class="mobile-footer-bc__partner"
               href="<?= htmlspecialchars((string) ($item['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"
               target="_blank"
               rel="noopener noreferrer">
              <img src="<?= htmlspecialchars((string) ($item['src'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   alt=""
                   loading="lazy">
            </a>
          <?php elseif ($itemType === 'text'): ?>
            <div class="mobile-footer-bc__legal"><?= (string) ($item['html'] ?? '') ?></div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($footerPayments)): ?>
  <section class="mobile-footer-bc__section">
    <h3>ÖDEMELER</h3>
    <div class="mobile-footer-bc__payments" aria-label="Ödeme yöntemleri">
      <?php foreach ($footerPayments as $payment): ?>
        <?php
        if (!is_array($payment)) {
            continue;
        }
        $paymentImage = (string) ($payment['image'] ?? '');
        if ($paymentImage === '') {
            continue;
        }
        ?>
        <span class="mobile-footer-bc__payment">
          <img src="<?= htmlspecialchars($paymentImage, ENT_QUOTES, 'UTF-8') ?>"
               alt="<?= htmlspecialchars((string) ($payment['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
               loading="lazy">
        </span>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <div class="mobile-footer-bc__copyright">
    <?= htmlspecialchars($footerCopyrightText, ENT_QUOTES, 'UTF-8') ?>
  </div>
</footer>
