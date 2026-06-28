<?php
if (!isset($providers) || !isset($jackpotEpoch)) {
    if (!class_exists('ApiJackpot', false)) {
        require_once (defined('API_PATH') ? API_PATH : dirname(__DIR__, 2) . '/api') . '/Jackpot.php';
    }
    $jackpotData = ApiJackpot::fetch();
    $jackpotEpoch = (string) ($jackpotData['epoch'] ?? date('Y-m-d H:i:s'));
    $providers = is_array($jackpotData['providers'] ?? null) ? $jackpotData['providers'] : [];
}
?>
<section class="jp-section" data-jackpot-epoch="<?= htmlspecialchars($jackpotEpoch, ENT_QUOTES, 'UTF-8') ?>">
  <div class="jp-bg">
    <div class="jp-deco jp-deco--cherry"></div>
    <div class="jp-deco jp-deco--strawberry"></div>
    <div class="jp-deco jp-deco--gem-blue"></div>
    <div class="jp-deco jp-deco--leaf"></div>
    <div class="jp-deco jp-deco--orb-purple"></div>
    <div class="jp-deco jp-deco--orb-pink"></div>

    <nav class="jp-tabs">
      <?php foreach ($providers as $i => $p): ?>
        <button
          class="jp-tab<?= $i === 0 ? ' jp-tab--active' : '' ?>"
          data-jp-provider="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>"
          type="button"
        ><?= htmlspecialchars($p['tab'], ENT_QUOTES, 'UTF-8') ?></button>
      <?php endforeach; ?>
    </nav>

    <?php foreach ($providers as $i => $p):
      $mainTier = null;
      $subTiers = [];
      foreach ($p['tiers'] as $tier) {
        if (!empty($tier['main'])) {
          $mainTier = $tier;
        } else {
          $subTiers[] = $tier;
        }
      }
      ?>
      <div
        class="jp-panel<?= $i === 0 ? ' jp-panel--active' : '' ?>"
        data-jp-panel="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>"
      >
        <h2 class="jp-provider-title"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></h2>

        <?php if ($mainTier): ?>
          <div class="jp-main-card">
            <span class="jp-main-label"><?= htmlspecialchars($mainTier['name'], ENT_QUOTES, 'UTF-8') ?></span>
            <span
              class="jp-main-amount jp-amount"
              data-jackpot-amount="<?= (float) $mainTier['amount'] ?>"
              data-jackpot-increment="<?= (float) $mainTier['increment'] ?>"
            ></span>
          </div>
        <?php endif; ?>

        <div class="jp-sub-cards">
          <?php foreach ($subTiers as $tier): ?>
            <div class="jp-sub-card">
              <span class="jp-sub-label"><?= htmlspecialchars($tier['name'], ENT_QUOTES, 'UTF-8') ?></span>
              <span
                class="jp-sub-amount jp-amount"
                data-jackpot-amount="<?= (float) $tier['amount'] ?>"
                data-jackpot-increment="<?= (float) $tier['increment'] ?>"
              ></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
