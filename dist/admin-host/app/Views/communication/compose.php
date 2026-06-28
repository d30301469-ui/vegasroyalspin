<?php

$flash = trim((string) ($flash ?? ''));
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">İletişim · Mesaj</span>
        <h1 class="hero-title">Yeni <span class="accent">mesaj</span></h1>
        <p class="hero-sub">Tema compose ekranı `mail_outbound_log` tablosuna gönderim kaydı oluşturur.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>">Gelen Kutusu</a>
        <button class="btn btn--primary" type="submit" form="composeForm">Gönder</button>
    </div>
</section>

<div class="grid">
    <section class="col-12 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Mail</span>
                <h2 class="card-title">Mesaj Oluştur</h2>
            </div>
            <span class="badge solid">Taslak</span>
        </div>
        <form id="composeForm" method="post" action="<?= htmlspecialchars(AdminAuth::url('/compose'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-grid">
                <div class="field span-2">
                    <label class="field-label" for="to_email">Alıcı email</label>
                    <div class="input-icon"><span class="ico"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span><input id="to_email" class="input" type="email" name="to_email" placeholder="user@example.com" required></div>
                </div>
                <div class="field span-2"><label class="field-label" for="subject">Konu</label><input id="subject" class="input" name="subject" type="text" required></div>
                <div class="field span-2"><label class="field-label" for="body">Mesaj</label><textarea id="body" class="textarea" name="body" rows="10" required></textarea><div class="field-help">İlk 500 karakter log preview olarak saklanır.</div></div>
            </div>
            <div class="form-actions"><span class="badge dot success">mail_outbound_log</span><span class="spacer"></span><a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>">Vazgeç</a><button class="btn btn--primary" type="submit">Mesajı Gönder</button></div>
        </form>
    </section>
</div>
