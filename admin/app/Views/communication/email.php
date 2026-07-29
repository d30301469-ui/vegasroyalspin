<?php

$mailbox = trim((string) ($mailbox ?? ''));
$imapConfigured = !empty($imapConfigured);
$inboxListUrl = (string) ($inboxListUrl ?? AdminAuth::url('/email/inbox/list'));
$emailSection = 'inbox';
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">Gelen <span class="accent">e-postalar</span></h1>
        <p class="hero-sub">
            <?php if ($mailbox !== ''): ?>
                IMAP ile <strong><?= htmlspecialchars($mailbox, ENT_QUOTES, 'UTF-8') ?></strong> gelen kutusu.
            <?php else: ?>
                Billion Mail IMAP gelen kutusu (E-posta → Ayarlar’dan SMTP kullanıcı/şifre gerekli).
            <?php endif; ?>
        </p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--ghost" type="button" data-inbox-reload>Yenile</button>
        <a class="btn btn--primary" href="<?= htmlspecialchars(AdminAuth::url('/email/send'), ENT_QUOTES, 'UTF-8') ?>">E-posta gönder</a>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<div
    id="email-inbox-panel"
    data-inbox-url="<?= htmlspecialchars($inboxListUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-inbox-configured="<?= $imapConfigured ? '1' : '0' ?>"
>
    <?php if (!$imapConfigured): ?>
        <div class="alert alert--danger" style="display:block;margin-bottom:12px;">
            IMAP gelen kutusu yapılandırılmamış. E-posta → Ayarlar bölümünden IMAP host, kullanıcı ve şifre bilgilerini girip
            “IMAP gelen kutusu aktif” seçeneğini işaretleyin.
        </div>
    <?php else: ?>
        <section class="card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">IMAP INBOX</span>
                    <h2 class="card-title">Gelen kutusu</h2>
                </div>
                <span class="badge dot">Yükleniyor</span>
            </div>
            <p class="field-help" style="padding:16px;">Mesajlar IMAP sunucusundan alınıyor…</p>
        </section>
    <?php endif; ?>
</div>

<?php if ($imapConfigured): ?>
<script>
(function () {
    var panel = document.getElementById('email-inbox-panel');
    if (!panel) return;
    var url = panel.getAttribute('data-inbox-url') || '';
    if (!url) return;
    var loading = false;

    function renderError(message) {
        panel.innerHTML = '';
        var alert = document.createElement('div');
        alert.className = 'alert alert--danger';
        alert.style.display = 'block';
        alert.textContent = message;
        panel.appendChild(alert);
    }

    function load() {
        if (loading) return;
        loading = true;
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var timer = window.setTimeout(function () {
            if (controller) controller.abort();
        }, 40000);

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller ? controller.signal : undefined
        })
            .then(function (response) {
                if (!response.ok) throw new Error('http_' + response.status);
                return response.text();
            })
            .then(function (html) {
                panel.innerHTML = html;
            })
            .catch(function () {
                renderError('Gelen kutusu yüklenemedi. IMAP sunucusu yanıt vermiyor olabilir; “Yenile” ile tekrar deneyin.');
            })
            .then(function () {
                window.clearTimeout(timer);
                loading = false;
            });
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest ? event.target.closest('[data-inbox-reload]') : null;
        if (!trigger) return;
        event.preventDefault();
        load();
    });

    load();
})();
</script>
<?php endif; ?>
