<?php

$flash = trim((string) ($flash ?? ''));
$emailSection = 'send';
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">E-posta <span class="accent">gönder</span></h1>
        <p class="hero-sub">Tek alıcıya e-posta gönderir; üye bulunan adrese gelen kutusu mesajı da düşer.</p>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($flash !== ''): ?>
    <div class="alert <?= stripos($flash, 'başar') !== false || stripos($flash, 'gonderildi') !== false || stripos($flash, 'gönderildi') !== false ? 'alert--success' : 'alert--danger' ?>" style="display:block;margin-bottom:12px;white-space:pre-wrap;">
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Yeni</span>
            <h2 class="card-title">Mesaj oluştur</h2>
        </div>
    </div>
    <form id="composeForm" method="post" action="<?= htmlspecialchars(AdminAuth::url('/email/send'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-grid">
            <div class="field span-2">
                <label class="field-label" for="to_email">Alıcı e-posta</label>
                <input id="to_email" class="input" type="email" name="to_email" placeholder="uye@ornek.com" required>
            </div>
            <div class="field span-2">
                <label class="field-label" for="subject">Konu</label>
                <input id="subject" class="input" name="subject" type="text" required>
            </div>
            <div class="field span-2">
                <label class="field-label" for="body">Mesaj</label>
                <textarea id="body" class="textarea" name="body" rows="10" required></textarea>
            </div>
        </div>
        <div class="form-actions">
            <span class="spacer"></span>
            <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email/inbox'), ENT_QUOTES, 'UTF-8') ?>">Vazgeç</a>
            <button class="btn btn--primary" type="submit">Gönder</button>
        </div>
    </form>
</section>
