<?php
/**
 * CM622 multi-select (desktop profile filters / forms).
 *
 * Vars:
 * - $ms_id          string  root id (unique)
 * - $ms_input_id    string  hidden input id (JS hooks)
 * - $ms_name        string  hidden input name (optional)
 * - $ms_title       string  floating label
 * - $ms_options     array   [value => label] or list of ['value'=>,'label'=>]
 * - $ms_selected    string  selected value
 * - $ms_required    bool    optional
 */
$ms_id = (string) ($ms_id ?? '');
$ms_input_id = (string) ($ms_input_id ?? $ms_id);
$ms_name = (string) ($ms_name ?? '');
$ms_title = (string) ($ms_title ?? '');
$ms_selected = (string) ($ms_selected ?? '');
$ms_required = !empty($ms_required);
$ms_options_raw = $ms_options ?? [];
$ms_options_norm = [];
foreach ($ms_options_raw as $k => $v) {
    if (is_array($v)) {
        $ms_options_norm[] = [
            'value' => (string) ($v['value'] ?? ''),
            'label' => (string) ($v['label'] ?? ($v['value'] ?? '')),
        ];
        continue;
    }
    $ms_options_norm[] = [
        'value' => (string) $k,
        'label' => (string) $v,
    ];
}
if ($ms_selected === '' && $ms_options_norm) {
    $hasEmptyOption = false;
    foreach ($ms_options_norm as $opt) {
        if ((string) $opt['value'] === '') {
            $hasEmptyOption = true;
            break;
        }
    }
    if (!$hasEmptyOption) {
        $ms_selected = (string) $ms_options_norm[0]['value'];
    }
}
$ms_selected_label = $ms_selected;
$ms_filled = false;
foreach ($ms_options_norm as $opt) {
    if ((string) $opt['value'] === (string) $ms_selected) {
        $ms_selected_label = (string) $opt['label'];
        /* '' => 'Tümü' like options must still float the label up */
        $ms_filled = true;
        break;
    }
}
?>
<div class="multi-select-bc" id="<?= htmlspecialchars($ms_id, ENT_QUOTES, 'UTF-8') ?>" data-cm622-ms="1" tabindex="0">
  <div class="form-control-bc select has-icon<?= $ms_filled ? ' valid filled' : '' ?>">
    <div class="form-control-label-bc inputs" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false">
      <div class="form-control-select-bc ellipsis"><?= htmlspecialchars($ms_selected_label, ENT_QUOTES, 'UTF-8') ?></div>
      <i class="form-control-icon-bc bc-i-small-arrow-down" aria-hidden="true"></i>
      <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
      <?php if ($ms_title !== ''): ?>
      <span class="form-control-title-bc ellipsis"><?= htmlspecialchars($ms_title, ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>
    <div class="multi-select-label-bc" role="listbox" hidden>
      <?php foreach ($ms_options_norm as $opt):
          $isActive = (string) $opt['value'] === (string) $ms_selected;
      ?>
      <label class="checkbox-control-content-bc<?= $isActive ? ' active' : '' ?>" role="option" aria-selected="<?= $isActive ? 'true' : 'false' ?>" data-option-value="<?= htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') ?>" data-option-label="<?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?>">
        <p class="checkbox-control-text-bc ellipsis"><?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?></p>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <input type="hidden"
         id="<?= htmlspecialchars($ms_input_id, ENT_QUOTES, 'UTF-8') ?>"
         <?= $ms_name !== '' ? 'name="' . htmlspecialchars($ms_name, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
         value="<?= htmlspecialchars($ms_selected, ENT_QUOTES, 'UTF-8') ?>"
         autocomplete="off"
         <?= $ms_required ? 'required' : '' ?>>
</div>
<?php
unset($ms_id, $ms_input_id, $ms_name, $ms_title, $ms_options, $ms_options_raw, $ms_options_norm, $ms_selected, $ms_selected_label, $ms_required, $ms_filled, $opt, $isActive, $k, $v);
