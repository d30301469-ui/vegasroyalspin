<?php

$flash = trim((string) ($flash ?? ''));
?>
<style>
    .signup-layout {
        align-items: start;
        display: grid;
        gap: 14px;
        grid-template-columns: minmax(0, 1.4fr) minmax(280px, 1fr);
        width: 100%;
    }
    .signup-layout > .card {
        min-width: 0;
        width: 100%;
    }
    .signup-layout .form-grid {
        display: grid;
        gap: 14px 16px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .signup-layout .form-grid .field,
    .signup-layout .input,
    .signup-layout .select {
        min-width: 0;
        width: 100%;
    }
    @media (max-width: 1100px) {
        .signup-layout {
            grid-template-columns: 1fr;
        }
        .signup-layout .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Pages · Signup</span>
        <h1 class="hero-title">Admin <span class="accent">ekle</span></h1>
        <p class="hero-sub">Oluşturulan şifre güvenli şekilde hashlenerek `admins` tablosuna kaydedilir.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/module?key=admins'), ENT_QUOTES, 'UTF-8') ?>">Admin listesi</a>
    </div>
</section>

<div class="signup-layout">
    <section class="card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Admin account</span>
                <h2 class="card-title">Yeni yetkili</h2>
            </div>
            <span class="badge solid">admins</span>
        </div>
        <form method="post" action="<?= htmlspecialchars(AdminAuth::url('/signup'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="username">Kullanıcı adı</label>
                    <input id="username" class="input" name="username" autocomplete="username" required>
                </div>
                <div class="field">
                    <label class="field-label" for="email">Email</label>
                    <input id="email" class="input" type="email" name="email" autocomplete="email" required>
                </div>
                <div class="field">
                    <label class="field-label" for="role">Rol</label>
                    <select id="role" class="select" name="role">
                        <option value="admin">admin</option>
                        <option value="super_admin">super_admin</option>
                        <option value="editor">editor</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="password">Şifre</label>
                    <input id="password" class="input" type="password" name="password" minlength="6" autocomplete="new-password" required>
                    <div class="field-help">En az 6 karakter. Kayıt sırasında hashlenir.</div>
                </div>
            </div>
            <div class="form-actions">
                <span class="badge dot success">CSRF korumalı</span>
                <span class="spacer"></span>
                <button class="btn btn--primary" type="submit">Admin oluştur</button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Access</span>
                <h2 class="card-title">Giriş bilgisi</h2>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px">
            <div class="alert info">
                <span class="ico"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                <div class="body">Admin girişi kullanıcı adıyla değil, `admins.email` ve `admins.password` ile yapılır.</div>
            </div>
            <a class="data-row" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="cell-name">Email ekranı</span>
                <span class="badge dot info">theme page</span>
            </a>
            <a class="data-row" href="<?= htmlspecialchars(AdminAuth::url('/forms'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="cell-name">Form merkezi</span>
                <span class="badge dot success">Aktif</span>
            </a>
        </div>
    </section>
</div>
