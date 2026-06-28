<section class="hero">
    <div class="hero-text">
        <span class="eyebrow" id="heroDate"><?= htmlspecialchars(date('l · F d · Y'), ENT_QUOTES, 'UTF-8') ?></span>
        <h1 class="hero-title">Blank <span class="accent">canvas</span></h1>
        <p class="hero-sub">Yeni admin modülleri için boş başlangıç şablonu.</p>
    </div>
    <div class="hero-actions"><a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/tables'), ENT_QUOTES, 'UTF-8') ?>">Add section</a></div>
</section>
<section class="card" style="min-height:360px;align-items:center;justify-content:center">
    <div style="text-align:center;color:var(--t-light);padding:60px 20px">
        <div style="width:56px;height:56px;margin:0 auto 18px;border-radius:14px;background:var(--bg-muted);color:var(--t-muted);display:grid;place-items:center"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 5v14M5 12h14"/></svg></div>
        <div style="font-family:'Inter Tight',sans-serif;font-weight:700;font-size:18px;color:var(--t-base);letter-spacing:-.018em;margin-bottom:6px">Empty card</div>
        <div style="font-size:13px;max-width:36ch;margin:0 auto">Bu alan yeni özel admin ekranları için temiz başlangıç noktasıdır.</div>
    </div>
</section>
