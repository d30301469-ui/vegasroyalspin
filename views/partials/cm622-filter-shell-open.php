<?php
/**
 * Open CM622 filter shell.
 * Vars: $cm622_filter_form_id, $cm622_filter_form_class, $cm622_filter_method, $cm622_filter_action, $cm622_filter_title
 */
$cm622_filter_form_id = (string) ($cm622_filter_form_id ?? '');
$cm622_filter_form_class = trim('filter-form-w-bc ' . (string) ($cm622_filter_form_class ?? ''));
$cm622_filter_method = (string) ($cm622_filter_method ?? 'get');
$cm622_filter_action = (string) ($cm622_filter_action ?? '');
$cm622_filter_title = (string) ($cm622_filter_title ?? (function_exists('__') ? __('game.filter') : 'FİLTRE'));
$cm622_filter_tag = !empty($cm622_filter_no_form) ? 'div' : 'form';
?>
<div class="componentFilterWrapper-bc cm622-profile-filter">
  <div class="componentFilterLabel-bc active" aria-hidden="true">
    <i class="componentFilterLabel-filter-i-bc bc-i-filter" aria-hidden="true"></i>
    <div class="componentFilterLabel-filter-bc"><p class="ellipsis"><?= htmlspecialchars($cm622_filter_title, ENT_QUOTES, 'UTF-8') ?></p></div>
    <i class="componentFilterChevron-bc bc-i-small-arrow-down" aria-hidden="true"></i>
  </div>
  <div class="componentFilterBody-bc">
    <div class="componentFilterElsWrapper-bc">
      <<?= $cm622_filter_tag ?>
        class="<?= htmlspecialchars($cm622_filter_form_class, ENT_QUOTES, 'UTF-8') ?>"
        <?= $cm622_filter_form_id !== '' ? 'id="' . htmlspecialchars($cm622_filter_form_id, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
        <?php if ($cm622_filter_tag === 'form'): ?>
        method="<?= htmlspecialchars($cm622_filter_method, ENT_QUOTES, 'UTF-8') ?>"
        action="<?= htmlspecialchars($cm622_filter_action, ENT_QUOTES, 'UTF-8') ?>"
        <?php endif; ?>
      >
        <div class="componentFilterBody-content">
