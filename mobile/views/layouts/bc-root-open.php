<?php
$bcRootExtra = (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true)
    ? ' has-header-info has-header-info-loyalty'
    : '';
?>
<div id="root" class="layout-bc<?= htmlspecialchars($bcRootExtra, ENT_QUOTES, 'UTF-8') ?>">
