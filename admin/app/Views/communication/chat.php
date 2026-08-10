<?php

$requests = is_array($requests ?? null) ? $requests : [];
?>
<section class="admin-surface">
<section class="hero mail-hero">
    <div class="hero-text">
        <span class="eyebrow">İletişim · Talepler</span>
        <h1 class="hero-title">Aranma Talepleri · <span class="accent"><?= count($requests) ?></span></h1>
        <p class="hero-sub">Üyelerin “beni ara” taleplerinin hızlı özeti. Durum güncellemesi için talep listesini açın.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/module?key=call-requests'), ENT_QUOTES, 'UTF-8') ?>">Talepleri aç</a>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Konuşmalar</span>
            <h2 class="card-title">Son talepler</h2>
        </div>
    </div>
    <div class="chat-frame">
        <div class="chat-messages">
            <?php if ($requests === []): ?>
                <div class="chat-row">
                    <div class="chat-stack">
                        <div class="chat-bubble">Bekleyen aranma talebi yok.</div>
                    </div>
                </div>
            <?php endif; ?>
            <?php foreach ($requests as $index => $request): ?>
                <?php
                $reqId = (string) ($request['id'] ?? '');
                $reqUserId = (string) ($request['user_id'] ?? '');
                $status = strtolower((string) ($request['status'] ?? ''));
                $canComplete = in_array($status, ['pending', 'new', 'open', ''], true);
                ?>
                <div class="chat-row <?= $index % 2 === 1 ? 'me' : '' ?>">
                    <div class="chat-avatar <?= $index % 2 === 1 ? 'me' : '' ?>"><?= htmlspecialchars(strtoupper(substr((string) ($request['full_name'] ?? $request['username'] ?? 'CR'), 0, 2)), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="chat-stack">
                        <div class="chat-bubble"><?= htmlspecialchars((string) ($request['message'] ?? 'Beni ara talebi'), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="chat-bubble"><?= htmlspecialchars(trim((string) ($request['phone'] ?? '') . ' · ' . (string) ($request['status'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="chat-ts">
                            <?= htmlspecialchars((string) ($request['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($reqUserId !== ''): ?>
                                · <a href="<?= htmlspecialchars(AdminAuth::url('/user?id=' . rawurlencode($reqUserId)), ENT_QUOTES, 'UTF-8') ?>">Üye</a>
                            <?php endif; ?>
                            <?php if ($reqId !== '' && $canComplete): ?>
                                · <form class="admin-inline-form" method="post" action="<?= htmlspecialchars(AdminAuth::url('/call-request/complete'), ENT_QUOTES, 'UTF-8') ?>" style="display:inline" data-admin-confirm="Bu aranma talebi tamamlandı olarak işaretlensin mi?">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($reqId, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" style="background:none;border:0;padding:0;color:inherit;font:inherit;cursor:pointer;text-decoration:underline">Tamamla</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
</section>
