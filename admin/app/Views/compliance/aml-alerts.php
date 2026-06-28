<?php

$flash = trim((string) ($flash ?? ''));
$status = trim((string) ($status ?? 'open'));
$total = max(0, (int) ($total ?? 0));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Uyumluluk · AML</span>
        <h1 class="hero-title">AML <span class="accent">Uyarıları</span></h1>
        <p class="hero-sub">Anti-money laundering uyarılarını inceleyin ve çözümleyin.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/compliance/aml-alerts?status=open')) ?>">Açık</a>
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/compliance/aml-alerts?status=resolved')) ?>">Çözülmüş</a>
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/compliance/aml-alerts?status=')) ?>">Tümü</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--success"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card">
    <div class="card-head"><h2 class="card-title"><?= $total ?> kayıt · <?= $text($status !== '' ? $status : 'all') ?></h2></div>
    <?php
    $resolveUrl = AdminAuth::url('/compliance/aml/resolve');
    require __DIR__ . '/_alerts-table.php';
    ?>
</section>
