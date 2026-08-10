<?php
/**
 * CM622 floating text/date form control.
 *
 * Vars:
 * - $fc_id, $fc_name, $fc_title, $fc_value
 * - $fc_type (text|date|...) default text
 * - $fc_inputmode optional
 * - $fc_autocomplete optional
 * - $fc_extra_class on form-control-bc (e.g. "has-icon")
 * - $fc_search_icon bool — append sport-search-icon
 * - $fc_required bool
 * - $fc_attrs string extra attributes on input
 */
$fc_id = (string) ($fc_id ?? '');
$fc_name = (string) ($fc_name ?? '');
$fc_title = (string) ($fc_title ?? '');
$fc_value = (string) ($fc_value ?? '');
$fc_type = (string) ($fc_type ?? 'text');
$fc_inputmode = (string) ($fc_inputmode ?? '');
$fc_autocomplete = (string) ($fc_autocomplete ?? 'off');
$fc_extra_class = trim((string) ($fc_extra_class ?? ''));
$fc_search_icon = !empty($fc_search_icon);
$fc_required = !empty($fc_required);
$fc_attrs = (string) ($fc_attrs ?? '');
$fc_filled = $fc_value !== '';
$fc_wrap_class = 'form-control-bc default' . ($fc_filled ? ' valid filled' : '') . ($fc_extra_class !== '' ? ' ' . $fc_extra_class : '');
?>
<div class="u-i-p-control-item-holder-bc">
  <div class="<?= htmlspecialchars($fc_wrap_class, ENT_QUOTES, 'UTF-8') ?>">
    <label class="form-control-label-bc inputs"<?= $fc_id !== '' ? ' for="' . htmlspecialchars($fc_id, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
      <input
        type="<?= htmlspecialchars($fc_type, ENT_QUOTES, 'UTF-8') ?>"
        class="form-control-input-bc"
        <?= $fc_id !== '' ? 'id="' . htmlspecialchars($fc_id, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
        <?= $fc_name !== '' ? 'name="' . htmlspecialchars($fc_name, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
        value="<?= htmlspecialchars($fc_value, ENT_QUOTES, 'UTF-8') ?>"
        autocomplete="<?= htmlspecialchars($fc_autocomplete, ENT_QUOTES, 'UTF-8') ?>"
        <?= $fc_inputmode !== '' ? 'inputmode="' . htmlspecialchars($fc_inputmode, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
        <?= $fc_required ? 'required' : '' ?>
        <?= $fc_attrs !== '' ? $fc_attrs : '' ?>
      >
      <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
      <?php if ($fc_title !== ''): ?>
      <span class="form-control-title-bc ellipsis"><?= htmlspecialchars($fc_title, ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </label>
  </div>
  <?php if ($fc_search_icon): ?>
  <i class="sport-search-icon bc-i-search" aria-hidden="true"></i>
  <?php endif; ?>
</div>
<?php
unset($fc_id, $fc_name, $fc_title, $fc_value, $fc_type, $fc_inputmode, $fc_autocomplete, $fc_extra_class, $fc_search_icon, $fc_required, $fc_attrs, $fc_filled, $fc_wrap_class);
