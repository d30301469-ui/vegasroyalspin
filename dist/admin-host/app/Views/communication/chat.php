<?php

$requests = is_array($requests ?? null) ? $requests : [];
$logs = is_array($logs ?? null) ? $logs : [];
?>
<section class="admin-surface">
<section class="hero mail-hero">
    <div class="hero-text">
        <span class="eyebrow">İletişim · Talepler</span>
        <h1 class="hero-title">Aranma Talepleri · <span class="accent"><?= count($requests) ?></span></h1>
        <p class="hero-sub">Chat teması `call_me_requests` ve `admin_logs` akışına uyarlanmıştır.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/module?key=call-requests'), ENT_QUOTES, 'UTF-8') ?>">Talepleri aç</a>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Konuşmalar</span>
            <h2 class="card-title">Hızlı Akış</h2>
        </div>
        <a class="card-action" href="<?= htmlspecialchars(AdminAuth::url('/module?key=logs'), ENT_QUOTES, 'UTF-8') ?>">Logları Aç <svg viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
    </div>
    <div class="chat-frame">
        <div class="chat-messages">
            <?php foreach ($requests as $index => $request): ?>
                <div class="chat-row <?= $index % 2 === 1 ? 'me' : '' ?>">
                    <div class="chat-avatar <?= $index % 2 === 1 ? 'me' : '' ?>"><?= htmlspecialchars(strtoupper(substr((string) ($request['full_name'] ?? $request['username'] ?? 'CR'), 0, 2)), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="chat-stack">
                        <div class="chat-bubble"><?= htmlspecialchars((string) ($request['message'] ?? 'Beni ara talebi'), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="chat-bubble"><?= htmlspecialchars((string) ($request['phone'] ?? '') . ' · ' . (string) ($request['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="chat-ts"><?= htmlspecialchars((string) ($request['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php foreach (array_slice($logs, 0, 3) as $log): ?>
                <div class="chat-row me">
                    <div class="chat-avatar me"><?= htmlspecialchars(strtoupper(substr((string) ($log['admin_username'] ?? 'AD'), 0, 2)), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="chat-stack"><div class="chat-bubble"><?= htmlspecialchars((string) ($log['action'] ?? '') . ' · ' . (string) ($log['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div><div class="chat-ts"><?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chat-input-row"><input class="chat-input" type="text" placeholder="Admin notu..."> <button class="chat-send" aria-label="Send"><svg viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button></div>
    </div>
</section>
</section>
