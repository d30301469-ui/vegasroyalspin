<?php

$alerts = is_array($alerts ?? null) ? $alerts : [];
$resolveUrl = (string) ($resolveUrl ?? '');
$ignoreUrl = (string) ($ignoreUrl ?? '');
$kind = (string) ($kind ?? 'aml'); // aml|risk
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$severityClass = static fn (string $value): string => match ($value) {
    'critical', 'high' => 'danger',
    'medium' => 'warning',
    default => 'info',
};
$ruleLabel = $kind === 'risk' ? 'Tip' : 'Kural';
$colspan = 11;
?>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Başlık</th>
                <th><?= $text($ruleLabel) ?></th>
                <th>Kullanıcı Adı</th>
                <th>İsim</th>
                <th>Soyisim</th>
                <th>Risk</th>
                <th>Önem</th>
                <th>Durum</th>
                <th>Tarih</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($alerts === []): ?>
                <tr><td colspan="<?= htmlspecialchars((string) $colspan, ENT_QUOTES, 'UTF-8') ?>">Kayıt bulunamadı.</td></tr>
            <?php else: ?>
                <?php foreach ($alerts as $alert): ?>
                    <?php
                    $id = (int) ($alert['id'] ?? 0);
                    $isOpen = (string) ($alert['status'] ?? '') === 'open';
                    $ruleOrType = (string) ($alert['rule_or_type'] ?? $alert['rule_code'] ?? $alert['alert_type'] ?? '');
                    $payloadRaw = (string) ($alert['payload_json'] ?? '');
                    $payloadPreview = '';
                    if ($payloadRaw !== '') {
                        $decoded = json_decode($payloadRaw, true);
                        if (is_array($decoded)) {
                            $payloadPreview = substr(json_encode($decoded, JSON_UNESCAPED_UNICODE), 0, 180);
                        }
                    }
                    $userUrl = !empty($alert['user_id']) ? AdminAuth::url('/user?id=' . (int) $alert['user_id']) : '';
                    ?>
                    <tr>
                        <td>#<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <strong><?= $text($alert['title'] ?? '') ?></strong>
                            <?php if (!empty($alert['description'])): ?>
                                <div class="muted"><?= $text(substr((string) $alert['description'], 0, 140)) ?></div>
                            <?php endif; ?>
                            <?php if ($payloadPreview !== ''): ?>
                                <div class="muted" style="font-size:10px;margin-top:4px"><?= $text($payloadPreview) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><code style="font-size:11px"><?= $text($ruleOrType !== '' ? $ruleOrType : '—') ?></code></td>
                        <td>
                            <?php if (!empty($alert['user_id'])): ?>
                                <?php if ($userUrl !== ''): ?>
                                    <a href="<?= $text($userUrl) ?>">#<?= (int) $alert['user_id'] ?> <?= $text($alert['username'] ?? '') ?></a>
                                <?php else: ?>
                                    #<?= (int) $alert['user_id'] ?> <?= $text($alert['username'] ?? '') ?>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= $text(trim((string) ($alert['name'] ?? '')) !== '' ? $alert['name'] : '—') ?></td>
                        <td><?= $text(trim((string) ($alert['surname'] ?? '')) !== '' ? $alert['surname'] : '—') ?></td>
                        <td>
                            <?php if (isset($alert['risk_score'])): ?>
                                <span class="badge <?= $text($severityClass((string) ($alert['risk_level'] ?? 'low'))) ?>">
                                    <?= (int) $alert['risk_score'] ?> · <?= $text($alert['risk_level'] ?? '') ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $text($severityClass((string) ($alert['severity'] ?? 'medium'))) ?>"><?= $text($alert['severity'] ?? 'medium') ?></span></td>
                        <?php
                        $alertStatusClass = match ((string) ($alert['status'] ?? 'open')) {
                            'open'     => 'warning',
                            'resolved' => 'success',
                            'ignored'  => 'primary',
                            default    => 'primary',
                        };
                        ?><td><span class="badge <?= htmlspecialchars($alertStatusClass, ENT_QUOTES, 'UTF-8') ?>"><?= $text($alert['status'] ?? '') ?></span></td>
                        <td><?= $text($alert['created_at'] ?? '') ?></td>
                        <td>
                            <?php if ($isOpen && ($resolveUrl !== '' || $ignoreUrl !== '')): ?>
                                <form method="post" action="<?= $text($resolveUrl) ?>" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                                    <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                    <input class="input" type="text" name="note" placeholder="Not" maxlength="500" style="max-width:120px;min-height:30px;padding:4px 8px;font-size:11px">
                                    <?php if ($resolveUrl !== ''): ?>
                                        <button class="btn btn--ghost btn--sm" type="submit">Çöz</button>
                                    <?php endif; ?>
                                    <?php if ($ignoreUrl !== ''): ?>
                                        <button class="btn btn--ghost btn--sm" type="submit" formaction="<?= $text($ignoreUrl) ?>">Yoksay</button>
                                    <?php endif; ?>
                                </form>
                            <?php elseif (!$isOpen): ?>
                                <span class="muted"><?= $text($alert['resolved_by'] ?? '') ?></span>
                                <?php if (!empty($alert['resolution_note'])): ?>
                                    <div class="muted" style="font-size:10px"><?= $text($alert['resolution_note']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
