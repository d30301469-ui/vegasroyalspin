<?php
/**
 * Son Kazananlar / En Çok Kazananlar — liste /api/v2/winners üzerinden (assets/js/winners.js).
 */
$winners_tab    = isset($_GET['winners_tab']) && $_GET['winners_tab'] === 'top' ? 'top' : 'recent';
$winners_period = isset($_GET['winners_period']) && in_array($_GET['winners_period'], ['day', 'week', 'month', 'all'], true)
    ? $_GET['winners_period']
    : 'day';
?>
<script>window.__WINNERS_API__='/api/v2/winners';window.__WINNERS_LIMIT__=8;</script>
<section class="winners-section" aria-label="Son kazananlar">
  <div class="winners-inner">
    <div class="winners-main-tabs" role="tablist">
      <button type="button" class="winners-main-tab <?= $winners_tab === 'recent' ? 'active' : '' ?>" data-winners-tab="recent" role="tab" aria-selected="<?= $winners_tab === 'recent' ? 'true' : 'false' ?>"><?= htmlspecialchars(__('winners.latest'), ENT_QUOTES, 'UTF-8') ?></button>
      <button type="button" class="winners-main-tab <?= $winners_tab === 'top' ? 'active' : '' ?>" data-winners-tab="top" role="tab" aria-selected="<?= $winners_tab === 'top' ? 'true' : 'false' ?>"><?= htmlspecialchars(__('winners.top'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div class="winners-period-tabs<?= $winners_tab === 'recent' ? ' winners-period-tabs--hidden' : '' ?>" role="tablist" aria-hidden="<?= $winners_tab === 'recent' ? 'true' : 'false' ?>">
      <button type="button" class="winners-period-tab <?= $winners_period === 'day' ? 'active' : '' ?>" data-period="day" role="tab"><?= htmlspecialchars(__('winners.day'), ENT_QUOTES, 'UTF-8') ?></button>
      <button type="button" class="winners-period-tab <?= $winners_period === 'week' ? 'active' : '' ?>" data-period="week" role="tab"><?= htmlspecialchars(__('winners.week'), ENT_QUOTES, 'UTF-8') ?></button>
      <button type="button" class="winners-period-tab <?= $winners_period === 'month' ? 'active' : '' ?>" data-period="month" role="tab"><?= htmlspecialchars(__('winners.month'), ENT_QUOTES, 'UTF-8') ?></button>
      <button type="button" class="winners-period-tab <?= $winners_period === 'all' ? 'active' : '' ?>" data-period="all" role="tab"><?= htmlspecialchars(__('winners.all'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div class="winners-list is-loading" role="list">
      <?php for ($i = 0; $i < 8; $i++): ?>
      <div class="winners-list-item-skeleton" role="presentation">
        <div class="winners-skeleton-icon"></div>
        <div class="winners-skeleton-info">
          <div class="winners-skeleton-line"></div>
          <div class="winners-skeleton-line"></div>
        </div>
        <div class="winners-skeleton-amount"></div>
      </div>
      <?php endfor; ?>
    </div>
  </div>
</section>
