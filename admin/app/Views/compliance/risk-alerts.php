<?php

$flash = trim((string) ($flash ?? ''));
$status = trim((string) ($status ?? 'open'));
$severity = trim((string) ($severity ?? ''));
$rule = trim((string) ($rule ?? ''));
$q = trim((string) ($q ?? ''));
$total = max(0, (int) ($total ?? 0));
$page = max(1, (int) ($page ?? 1));
$summary = is_array($summary ?? null) ? $summary : [];
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$base = AdminAuth::url('/compliance/risk-alerts');
$query = static function (array $extra) use ($status, $severity, $rule, $q, $base): string {
    $params = array_filter([
        'status' => $extra['status'] ?? $status,
        'severity' => $extra['severity'] ?? $severity,
        'rule' => $extra['rule'] ?? $rule,
        'q' => $extra['q'] ?? $q,
        'page' => $extra['page'] ?? null,
    ], static fn ($v) => $v !== null && $v !== '');
    return $base . ($params !== [] ? ('?' . http_build_query($params)) : '');
};
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Uyumluluk · Risk</span>
        <h1 class="hero-title">Risk <span class="accent">Uyarıları</span></h1>
        <p class="hero-sub">Çoklu çekim, telefon paylaşımı, KYC ve hız sinyallerini yönetin.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text($query(['status' => 'open', 'page' => null])) ?>">Açık</a>
        <a class="btn btn--ghost" href="<?= $text($query(['status' => 'resolved', 'page' => null])) ?>">Çözülmüş</a>
        <a class="btn btn--ghost" href="<?= $text($query(['status' => 'ignored', 'page' => null])) ?>">Yoksayılan</a>
        <a class="btn btn--ghost" href="<?= $text($query(['status' => '', 'page' => null])) ?>">Tümü</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--success"><?= $text($flash) ?></div>
<?php endif; ?>

<section class="card" style="margin-bottom:14px">
    <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
        <div><div class="muted">Açık</div><strong><?= (int) ($summary['open'] ?? 0) ?></strong></div>
        <div><div class="muted">Critical</div><strong><?= (int) ($summary['critical'] ?? 0) ?></strong></div>
        <div><div class="muted">High</div><strong><?= (int) ($summary['high'] ?? 0) ?></strong></div>
        <div><div class="muted">Bugün çözülen</div><strong><?= (int) ($summary['resolved_today'] ?? 0) ?></strong></div>
    </div>
</section>

<div class="cmp-charts">
    <div class="cmp-chart-card">
        <div class="cmp-chart-head"><h3 class="cmp-chart-title">Açık önem dağılımı</h3></div>
        <div class="cmp-chart-wrap"><canvas id="risk-severity"></canvas></div>
    </div>
    <div class="cmp-chart-card">
        <div class="cmp-chart-head"><h3 class="cmp-chart-title">Durum dağılımı</h3></div>
        <div class="cmp-chart-wrap"><canvas id="risk-status"></canvas></div>
    </div>
    <div class="cmp-chart-card wide">
        <div class="cmp-chart-head"><h3 class="cmp-chart-title">Açık risk tipleri</h3></div>
        <div class="cmp-chart-wrap"><canvas id="risk-rules"></canvas></div>
    </div>
    <div class="cmp-chart-card full">
        <div class="cmp-chart-head"><h3 class="cmp-chart-title">14 günlük trend</h3></div>
        <div class="cmp-chart-wrap tall"><canvas id="risk-trend"></canvas></div>
    </div>
</div>
<?php
$chartPrefix = 'risk';
$chartData = is_array($chartData ?? null) ? $chartData : [];
require __DIR__ . '/_charts-boot.php';
?>

<section class="card" style="margin-bottom:14px">
    <form method="get" action="<?= $text($base) ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">
        <input type="hidden" name="status" value="<?= $text($status) ?>">
        <label>Önem
            <select class="input" name="severity">
                <option value="">Tümü</option>
                <?php foreach (['critical', 'high', 'medium', 'low'] as $sev): ?>
                    <option value="<?= $text($sev) ?>" <?= $severity === $sev ? 'selected' : '' ?>><?= $text($sev) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tip
            <select class="input" name="rule">
                <option value="">Tümü</option>
                <?php
                $riskTypes = [
                    'multiple_pending_withdrawals', 'new_account_large_deposit', 'shared_phone',
                    'kyc_missing_high_balance', 'withdraw_velocity',
                ];
                foreach ($riskTypes as $code): ?>
                    <option value="<?= $text($code) ?>" <?= $rule === $code ? 'selected' : '' ?>><?= $text($code) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Üye ara
            <input class="input" type="search" name="q" value="<?= $text($q) ?>" placeholder="isim, kullanıcı, id">
        </label>
        <button class="btn btn--ghost" type="submit">Filtrele</button>
    </form>
</section>

<section class="card">
    <div class="card-head"><h2 class="card-title"><?= htmlspecialchars($total, ENT_QUOTES, 'UTF-8') ?> kayıt · <?= $text($status !== '' ? $status : 'all') ?></h2></div>
    <?php
    $kind = 'risk';
    $resolveUrl = AdminAuth::url('/compliance/risk/resolve');
    $ignoreUrl = AdminAuth::url('/compliance/risk/ignore');
    require __DIR__ . '/_alerts-table.php';
    ?>
</section>
