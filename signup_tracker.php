<?php
/**
 * Eski referans linkleri (/signup_tracker.php?ref=CODE) için geriye dönük uyumluluk.
 * Güncel kısa link /r/{CODE} olup front controller tarafından karşılanır
 * (core/legacy_dispatch.php). Tıklama kaydı orada yapılır.
 */

declare(strict_types=1);

$ref = (string) ($_GET['ref'] ?? '');
$target = preg_match('/^[A-Za-z0-9_-]{1,64}$/', $ref) === 1
    ? '/r/' . $ref
    : '/';

header('Location: ' . $target, true, 302);
exit;
