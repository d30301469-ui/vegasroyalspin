<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Sistem · Butonlar</span>
        <h1 class="hero-title">Buton <span class="accent">kütüphanesi</span></h1>
        <p class="hero-sub">Admin panelinde kullanılan ana aksiyon butonları ve link kalıpları.</p>
    </div>
</section>
<div class="grid">
    <section class="col-12 card">
        <div class="card-head"><div class="card-title-wrap"><span class="eyebrow">Aksiyonlar</span><h2 class="card-title">Buton durumları</h2></div></div>
        <div class="demo-row"><a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">Primary</a><a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/tables'), ENT_QUOTES, 'UTF-8') ?>">Ghost</a><button class="btn btn--danger" type="button">Danger</button><button class="btn btn--soft-primary" type="button">Soft</button><button class="btn btn--primary" type="button" disabled>Disabled</button></div>
    </section>
</div>
