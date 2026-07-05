<?php

declare(strict_types=1);

$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$users = is_array($users ?? null) ? $users : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<style>
    .bgaming-campaign-grid { display:grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap:18px; align-items:start; }
    .bgaming-campaign-card { background: var(--bg-card); border:1px solid var(--border); border-radius:18px; box-shadow: var(--shadow-card); }
    .bgaming-campaign-head { padding:18px 20px; border-bottom:1px solid var(--border); }
    .bgaming-campaign-body { padding:18px 20px; }
    .bgaming-campaign-table { width:100%; border-collapse:collapse; }
    .bgaming-campaign-table th, .bgaming-campaign-table td { padding:12px 10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .bgaming-campaign-table th { color: var(--t-muted); font-size:12px; text-transform:uppercase; letter-spacing:.06em; }
    .bgaming-meta { color: var(--t-muted); font-size:13px; }
    .bgaming-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; }
    .bgaming-listbox { min-height: 164px; padding: 8px; }
    .bgaming-listbox option { padding: 8px 10px; border-radius: 8px; }
    .bgaming-listbox option:checked { background: color-mix(in oklab, var(--brand, #2f6fed) 16%, var(--bg-card)); color: var(--t-base); }
    .bgaming-help-box {
        margin-top: 10px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: color-mix(in oklab, var(--bg-card) 86%, var(--brand, #2f6fed) 3%);
        padding: 10px 12px;
        color: var(--t-muted);
        font-size: 13px;
        line-height: 1.45;
    }
    @media (max-width: 1080px) { .bgaming-campaign-grid { grid-template-columns:1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · BGaming</span>
        <h1 class="hero-title">BGaming <span class="accent">Kampanya Atama</span></h1>
        <p class="hero-sub">Oluşturulmuş kampanyaları kullanıcı hesaplarına buradan atayın.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $text(AdminAuth::url('/bgaming/campaigns')) ?>">Kampanya Oluştur</a>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<div class="bgaming-campaign-grid">
    <section class="bgaming-campaign-card">
        <div class="bgaming-campaign-head">
            <h2 style="margin:0;font-size:18px">Kullanıcıya Ata</h2>
        </div>
        <div class="bgaming-campaign-body">
            <form method="post" action="<?= $text(AdminAuth::url('/bgaming/campaigns/assign')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <div class="field">
                    <label class="field-label" for="assign_campaign_search">Kampanya</label>
                    <input id="assign_campaign_search" class="input" type="text" placeholder="Kampanya ara...">
                    <select id="assign_campaign_id" class="input bgaming-listbox" name="campaign_id" required size="7">
                        <?php foreach ($campaigns as $row): ?>
                            <option value="<?= (int) ($row['id'] ?? 0) ?>" data-search="<?= $text(strtolower((string) (($row['title'] ?? '') . ' ' . ($row['campaign_code'] ?? '') . ' ' . ($row['campaign_type'] ?? '')))) ?>"><?= $text(($row['title'] ?? '') . ' [' . ($row['campaign_code'] ?? '') . ']') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="assign_user_search">Kullanıcı</label>
                    <input id="assign_user_search" class="input" type="text" placeholder="Kullanıcı ara (id, username, email)">
                    <select id="assign_user_id" class="input bgaming-listbox" name="user_id" required size="10">
                        <?php foreach ($users as $user): ?>
                            <?php
                            $userLabel = '#' . (int) ($user['id'] ?? 0)
                                . ' · ' . (string) ($user['username'] ?? '');
                            if (!empty($user['name']) || !empty($user['surname'])) {
                                $userLabel .= ' (' . trim((string) ($user['name'] ?? '') . ' ' . (string) ($user['surname'] ?? '')) . ')';
                            }
                            if (!empty($user['email'])) {
                                $userLabel .= ' - ' . (string) $user['email'];
                            }
                            if ((int) ($user['banned'] ?? 0) === 1) {
                                $userLabel .= ' [BANNED]';
                            }
                            ?>
                            <option value="<?= (int) ($user['id'] ?? 0) ?>" data-search="<?= $text(strtolower($userLabel)) ?>"><?= $text($userLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bgaming-help-box">
                    Kampanya atama sadece panelde oluşturulmuş kampanyalar için çalışır.
                    API testinde freespin callback geldiğinde sistem issue_id ile otomatik kayıt da oluşturur.
                </div>
                <div class="form-actions" style="margin-top:16px">
                    <button class="btn btn--primary" type="submit">Kampanyayı Ata</button>
                </div>
            </form>
        </div>
    </section>

    <section class="bgaming-campaign-card">
        <div class="bgaming-campaign-head">
            <h2 style="margin:0;font-size:18px">Son Atamalar</h2>
        </div>
        <div class="bgaming-campaign-body" style="padding-top:4px">
            <table class="bgaming-campaign-table">
                <thead>
                    <tr>
                        <th>Kampanya</th>
                        <th>Kullanıcı</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($assignments === []): ?>
                        <tr><td colspan="3" class="bgaming-meta">Henüz atama yapılmadı.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= $text($row['title'] ?? '') ?></strong><br>
                                    <span class="bgaming-code"><?= $text($row['campaign_code'] ?? '') ?></span>
                                </td>
                                <td>#<?= (int) ($row['user_id'] ?? 0) ?><?php if (!empty($row['username'])): ?><div class="bgaming-meta"><?= $text($row['username']) ?></div><?php endif; ?></td>
                                <td><?= $text((string) ($row['status'] ?? 'assigned')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function bindFilter(inputId, selectId) {
        var input = document.getElementById(inputId);
        var select = document.getElementById(selectId);
        if (!input || !select) {
            return;
        }

        var options = Array.prototype.slice.call(select.options);
        function applyFilter() {
            var q = (input.value || '').toLowerCase().trim();
            options.forEach(function (opt) {
                var haystack = (opt.getAttribute('data-search') || opt.text || '').toLowerCase();
                opt.hidden = q !== '' && haystack.indexOf(q) === -1;
            });

            var selected = select.options[select.selectedIndex] || null;
            if (selected && selected.hidden) {
                select.selectedIndex = -1;
            }
        }

        input.addEventListener('input', applyFilter);
    }

    bindFilter('assign_campaign_search', 'assign_campaign_id');
    bindFilter('assign_user_search', 'assign_user_id');
});
</script>
