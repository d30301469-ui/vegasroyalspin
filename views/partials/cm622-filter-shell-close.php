<?php
/**
 * Close CM622 filter shell.
 * Vars: $cm622_filter_submit_label, $cm622_filter_submit_id, $cm622_filter_submit_type, $cm622_filter_no_form
 */
$cm622_filter_submit_label = (string) ($cm622_filter_submit_label ?? (function_exists('__') ? __('profile.show') : 'Göster'));
$cm622_filter_submit_id = (string) ($cm622_filter_submit_id ?? '');
$cm622_filter_submit_type = (string) ($cm622_filter_submit_type ?? 'submit');
$cm622_filter_tag = !empty($cm622_filter_no_form) ? 'div' : 'form';
?>
        <div class="u-i-p-c-footer-bc">
          <button
            class="btn a-color"
            type="<?= htmlspecialchars($cm622_filter_submit_type, ENT_QUOTES, 'UTF-8') ?>"
            <?= $cm622_filter_submit_id !== '' ? 'id="' . htmlspecialchars($cm622_filter_submit_id, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
            title="<?= htmlspecialchars($cm622_filter_submit_label, ENT_QUOTES, 'UTF-8') ?>"
          ><span><?= htmlspecialchars($cm622_filter_submit_label, ENT_QUOTES, 'UTF-8') ?></span></button>
        </div>
      </<?= $cm622_filter_tag ?>>
    </div>
  </div>
</div>
<?php
unset($cm622_filter_form_id, $cm622_filter_form_class, $cm622_filter_method, $cm622_filter_action, $cm622_filter_title, $cm622_filter_submit_label, $cm622_filter_submit_id, $cm622_filter_submit_type, $cm622_filter_no_form, $cm622_filter_tag);
