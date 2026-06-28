<?php
// Varsayılan: platipus. Örnek: /casino/ veya /casino/?default=evolution
if (!isset($_GET['default'])) {
    $_GET['default'] = 'platipus';
}
include __DIR__ . '/casino.php';
