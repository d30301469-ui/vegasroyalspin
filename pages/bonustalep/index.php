<?php
require_once dirname(__DIR__, 2) . '/core/bootstrap.php';
require_once dirname(__DIR__, 2) . '/services/ProfileApiHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    require_once dirname(__DIR__, 2) . '/config/frontend_session.php';
    metropol_frontend_session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || empty($_SESSION['username'])) {
    header('Location: /login');
    exit;
}

$username = trim((string) $_SESSION['username']);
$user = ProfileApiHelper::profileByUsername($username);

$firstName = trim((string) ($user['first_name'] ?? $user['name'] ?? ''));
$surname = trim((string) ($user['surname'] ?? $user['last_name'] ?? ''));
$fullName = trim($firstName . ' ' . $surname);
if ($fullName === '') {
    $fullName = 'Belirtilmemis';
}
$authSharedJsPath = dirname(__DIR__, 2) . '/assets/js/auth-shared.js';
$authSharedJsVer = (string) ((is_file($authSharedJsPath) ? filemtime($authSharedJsPath) : '1') . '-' . (is_file($authSharedJsPath) ? filesize($authSharedJsPath) : '0'));
$toastifyHelperJsPath = dirname(__DIR__, 2) . '/assets/js/toastify-helper.js';
$toastifyHelperJsVer = (string) ((is_file($toastifyHelperJsPath) ? filemtime($toastifyHelperJsPath) : '1') . '-' . (is_file($toastifyHelperJsPath) ? filesize($toastifyHelperJsPath) : '0'));
?>
<?php require VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>

<section class="mainWrap bonus-claim-page">
    <div class="centerWrap bonus-claim-wrap">
        <div class="bonus-claim-shell">
            <header class="bonus-claim-header">
                <p class="bonus-claim-kicker">Oyuncu Bonus Merkezi</p>
                <h1>Bonus Talep</h1>
                <p class="bonus-claim-subtitle">Aktif bonuslarinizi tek ekrandan inceleyip talep edebilirsiniz.</p>
            </header>

            <section class="bonus-member-card" aria-label="Kullanici bilgileri">
                <div class="bonus-member-item">
                    <span class="bonus-member-label">Kullanici Adi</span>
                    <strong id="memberUsername"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="bonus-member-item">
                    <span class="bonus-member-label">Isim Soyisim</span>
                    <strong id="memberFullname"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
            </section>

            <section class="bonus-catalog" aria-label="Bonus katalogu">
                <div class="bonus-tabs" role="tablist" aria-label="Bonus kategorileri">
                    <button type="button" class="bonus-tab active" data-type="yatirim" onclick="showBonuses('yatirim')">Yatirim Bonuslari</button>
                    <button type="button" class="bonus-tab" data-type="kayip" onclick="showBonuses('kayip')">Kayip Bonuslari</button>
                    <button type="button" class="bonus-tab" data-type="deneme" onclick="showBonuses('deneme')">Deneme Bonuslari</button>
                </div>
                <div class="bonus-list" id="bonus-list">
                    <p class="bonus-loading">Bonuslar yukleniyor...</p>
                </div>
            </section>

            <section class="bonus-history-card" aria-label="Bonus talep gecmisi">
                <div class="bonus-history-head">
                    <h2>Son Talepler</h2>
                    <button type="button" class="bonus-history-refresh" onclick="loadBonusHistory()">Yenile</button>
                </div>
                <p id="bonusHistoryStatus" class="bonus-history-status" role="status" aria-live="polite"></p>
                <div class="bonus-history-table-wrap">
                    <table class="bonus-history-table">
                        <thead>
                            <tr>
                                <th>Bonus</th>
                                <th>Tarih</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody id="bonusHistoryBody">
                            <tr>
                                <td colspan="3">Yukleniyor...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</section>

<div id="confirmModal" class="bonus-modal" aria-hidden="true">
    <div class="bonus-modal-content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <h2 id="modalTitle">Bonus Onayi</h2>
        <p id="modalText">Bu bonusu almak istediginizden emin misiniz?</p>
        <div class="bonus-modal-actions">
            <button type="button" class="btn-cancel" onclick="closeModal()">Vazgec</button>
            <button type="button" class="btn-confirm" id="confirmBonusBtn">Onayla</button>
        </div>
    </div>
</div>

<script src="/assets/js/auth-shared.js?v=<?= rawurlencode($authSharedJsVer) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.js"></script>
<script src="/assets/js/toastify-helper.js?v=<?= rawurlencode($toastifyHelperJsVer) ?>"></script>
<script>
let selectedBonus = null;
let selectedPromotionId = 0;
let activeBonusType = 'yatirim';
let promotions = [];
let claimPolicy = {};
let hasConfirmedDeposit = false;

function depositRequiredMessage() {
    const message = claimPolicy && typeof claimPolicy.depositRequiredMessage === 'string'
        ? claimPolicy.depositRequiredMessage.trim()
        : '';
    return message || 'Bu bonustan faydalanabilmeniz icin yatirim yapmaniz gerekmektedir.';
}

function canClaimBonus() {
    const requiresDeposit = !!(claimPolicy && claimPolicy.requiresConfirmedDeposit);
    return !requiresDeposit || hasConfirmedDeposit;
}

function showClaimBlockedWarning() {
    MaltabetToast.error(depositRequiredMessage(), 'Yatirim Sarti');
}

function openModal(bonusTuru, promotionId) {
    if (!canClaimBonus()) {
        showClaimBlockedWarning();
        return;
    }
    selectedBonus = bonusTuru;
    selectedPromotionId = parseInt(promotionId || 0, 10) || 0;

    document.getElementById('modalTitle').innerText = bonusTuru;
    document.getElementById('modalText').innerText = `${bonusTuru} talebinizi onayliyor musunuz?`;
    document.getElementById('confirmModal').style.display = 'flex';
    document.getElementById('confirmModal').setAttribute('aria-hidden', 'false');
}

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
    document.getElementById('confirmModal').setAttribute('aria-hidden', 'true');
}

function memberApiHeaders(extra) {
    var Shared = window.BetcoAuthShared || {};
    if (Shared.memberAuthHeaders) {
        return Shared.memberAuthHeaders(extra);
    }
    const headers = Object.assign({}, extra || {});
    const csrf = (window.__CSRF_TOKEN__ || '').trim();
    if (csrf) {
        headers['X-CSRF-Token'] = csrf;
    }
    return headers;
}

function memberApiUrl(path) {
    var Shared = window.BetcoAuthShared || {};
    return Shared.apiUrl ? Shared.apiUrl(path) : path;
}

function memberCredentials() {
    var Shared = window.BetcoAuthShared || {};
    return Shared.memberCredentials ? Shared.memberCredentials() : 'same-origin';
}

document.getElementById('confirmBonusBtn').addEventListener('click', () => {
    closeModal();
    bonusTalep(selectedBonus);
});

function bonusTalep(bonusTuru) {
    if (!canClaimBonus()) {
        showClaimBlockedWarning();
        return;
    }
    const body = selectedPromotionId > 0
        ? { promotionId: selectedPromotionId }
        : { bonusTitle: bonusTuru };

    fetch(memberApiUrl('/api/v2/bonus-claim'), {
        method: 'POST',
        credentials: memberCredentials(),
        headers: memberApiHeaders({ 'Content-Type': 'application/json', 'Accept': 'application/json' }),
        body: JSON.stringify(body)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            MaltabetToast.success(data.message || 'Bonus talebiniz alindi.', 'Basarili');
            loadBonusHistory();
        } else {
            MaltabetToast.error(data.message || 'Bonus talebi olusturulamadi.', 'Hata');
        }
    })
    .catch(() => {
        MaltabetToast.error('Bir hata olustu. Lutfen tekrar deneyin.', 'Sunucu Hatasi');
    });
}

function promotionMatchesType(promo, type) {
    const text = `${promo.title || ''} ${promo.type || ''} ${promo.category || ''}`.toLocaleLowerCase('tr-TR');
    if (type === 'kayip') return text.includes('kayip') || text.includes('kayıp') || text.includes('iade');
    if (type === 'deneme') return text.includes('deneme') || text.includes('free') || text.includes('freespin') || text.includes('freebet');
    return text.includes('yatirim') || text.includes('yatırım') || text.includes('casino') || text.includes('slot') || text.includes('pragmatic') || (!promotionMatchesType(promo, 'kayip') && !promotionMatchesType(promo, 'deneme'));
}

function renderBonuses(type) {
    const bonusList = document.getElementById('bonus-list');
    const filtered = promotions.filter(promo => promotionMatchesType(promo, type));

    if (!filtered.length) {
        bonusList.innerHTML = '<p class="bonus-empty">Bu kategoride aktif bonus bulunmuyor.</p>';
        return;
    }

    bonusList.innerHTML = '';

    if (!canClaimBonus()) {
        const warning = document.createElement('p');
        warning.className = 'claim-warning';
        warning.textContent = depositRequiredMessage();
        bonusList.appendChild(warning);
    }

    filtered.forEach(promo => {
        const title = String(promo.title || 'Bonus');
        const id = parseInt(promo.id || promo.promotionId || 0, 10) || 0;

        const card = document.createElement('article');
        card.className = 'bonus-card';

        const cardTitle = document.createElement('h3');
        cardTitle.textContent = title;

        const cardDesc = document.createElement('p');
        const desc = String(promo.description || promo.long_description || 'Aktif bonus kampanyasi.');
        cardDesc.textContent = desc.length > 120 ? `${desc.slice(0, 117)}...` : desc;

        const cardBtn = document.createElement('button');
        cardBtn.type = 'button';
        cardBtn.textContent = 'Bonus Talep Et';
        cardBtn.disabled = !canClaimBonus();
        if (cardBtn.disabled) {
            cardBtn.title = depositRequiredMessage();
        }
        cardBtn.addEventListener('click', () => openModal(title, id));

        card.appendChild(cardTitle);
        card.appendChild(cardDesc);
        card.appendChild(cardBtn);
        bonusList.appendChild(card);
    });
}

function loadPromotions() {
    fetch(memberApiUrl('/api/v2/content/promotions'), {
        credentials: memberCredentials(),
        headers: { 'Accept': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        claimPolicy = data && data.data && typeof data.data.claimPolicy === 'object' && data.data.claimPolicy
            ? data.data.claimPolicy
            : {};
        hasConfirmedDeposit = !!(data && data.data && data.data.viewer && data.data.viewer.hasConfirmedDeposit);
        promotions = data && data.success && data.data && Array.isArray(data.data.promotions)
            ? data.data.promotions
            : [];
        renderBonuses(activeBonusType);
    })
    .catch(() => {
        document.getElementById('bonus-list').innerHTML = '<p class="bonus-empty">Bonuslar yuklenemedi.</p>';
    });
}

function showBonuses(type) {
    activeBonusType = type;
    const buttons = document.querySelectorAll('.bonus-tab');
    buttons.forEach(button => {
        button.classList.toggle('active', button.getAttribute('data-type') === type);
    });
    renderBonuses(type);
}

function claimStatusText(value) {
    const status = String(value || '').toLowerCase();
    if (status === 'approved') return 'Onaylandi';
    if (status === 'rejected') return 'Reddedildi';
    if (status === 'pending') return 'Beklemede';
    return '-';
}

function formatDate(value) {
    const raw = String(value || '').trim();
    if (!raw) return '-';
    const date = new Date(raw.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return raw;
    return date.toLocaleString('tr-TR');
}

function loadBonusHistory() {
    const tbody = document.getElementById('bonusHistoryBody');
    const status = document.getElementById('bonusHistoryStatus');
    tbody.innerHTML = '<tr><td colspan="3">Yukleniyor...</td></tr>';
    status.textContent = '';

    fetch(memberApiUrl('/api/v2/bonus-claims-me?limit=20'), {
        credentials: memberCredentials(),
        headers: { 'Accept': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (!data || !data.success) {
            status.textContent = (data && data.message) ? data.message : 'Gecmis bonus kayitlari alinamadi.';
            tbody.innerHTML = '<tr><td colspan="3">Kayit bulunamadi.</td></tr>';
            return;
        }

        const claims = data && data.data && Array.isArray(data.data.claims)
            ? data.data.claims
            : [];

        if (!claims.length) {
            tbody.innerHTML = '<tr><td colspan="3">Henuz bonus talebiniz bulunmuyor.</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        claims.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${(item.bonusName || '-')}</td>
                <td>${formatDate(item.createdAt || item.created_at)}</td>
                <td>${claimStatusText(item.status)}</td>
            `;
            tbody.appendChild(tr);
        });
    })
    .catch(() => {
        status.textContent = 'Bonus gecmisi yuklenirken hata olustu.';
        tbody.innerHTML = '<tr><td colspan="3">Kayit bulunamadi.</td></tr>';
    });
}

loadPromotions();
loadBonusHistory();
</script>

<style>
.bonus-claim-page {
    padding: 24px 0 48px;
}

.bonus-claim-wrap {
    max-width: 1180px;
}

.bonus-claim-shell {
    color: #f4f6fb;
    display: grid;
    gap: 18px;
}

.bonus-claim-header {
    border-radius: 16px;
    padding: 24px;
    background: linear-gradient(135deg, #1a202e 0%, #121722 55%, #8f2d12 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.bonus-claim-kicker {
    margin: 0;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #f1a57a;
}

.bonus-claim-header h1 {
    margin: 8px 0 6px;
    font-size: 30px;
    line-height: 1.1;
    color: #ffffff;
}

.bonus-claim-subtitle {
    margin: 0;
    color: rgba(255, 255, 255, 0.78);
}

.bonus-member-card {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.bonus-member-item {
    background: #1a1f2b;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 14px 16px;
    min-height: 76px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.bonus-member-label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.62);
    margin-bottom: 6px;
}

.bonus-member-item strong {
    font-size: 18px;
    line-height: 1.25;
    color: #ffffff;
    word-break: break-word;
}

.bonus-catalog,
.bonus-history-card {
    background: #141926;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 18px;
}

.bonus-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.bonus-tab {
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: #1f2532;
    color: #d7deef;
    border-radius: 999px;
    padding: 9px 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.bonus-tab.active {
    background: linear-gradient(90deg, #ef5b2f, #ff9358);
    border-color: transparent;
    color: #ffffff;
}

.bonus-list {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.bonus-card {
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: linear-gradient(180deg, #1f2534 0%, #181e2a 100%);
    border-radius: 12px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    min-height: 178px;
}

.bonus-card h3 {
    margin: 0 0 8px;
    font-size: 17px;
    line-height: 1.2;
    color: #ffffff;
}

.bonus-card p {
    margin: 0 0 12px;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.74);
    flex: 1;
}

.bonus-card button {
    border: 0;
    border-radius: 10px;
    padding: 10px 12px;
    font-weight: 700;
    background: linear-gradient(90deg, #f84a3a 0%, #f29a4b 100%);
    color: #fff;
    cursor: pointer;
    transition: filter 0.2s ease, transform 0.2s ease;
}

.bonus-card button:hover {
    filter: brightness(1.07);
    transform: translateY(-1px);
}

.bonus-card button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none;
}

.claim-warning,
.bonus-empty,
.bonus-loading {
    grid-column: 1 / -1;
    margin: 0;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 14px;
}

.claim-warning {
    background: rgba(226, 80, 65, 0.2);
    border: 1px solid rgba(226, 80, 65, 0.6);
    color: #ffd7d2;
}

.bonus-empty,
.bonus-loading {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #d0d7e7;
}

.bonus-history-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    gap: 10px;
}

.bonus-history-head h2 {
    margin: 0;
    color: #fff;
    font-size: 20px;
}

.bonus-history-refresh {
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: #1f2532;
    color: #fff;
    border-radius: 10px;
    padding: 7px 12px;
    font-weight: 600;
    cursor: pointer;
}

.bonus-history-status {
    margin: 0 0 10px;
    font-size: 13px;
    color: #ffb198;
}

.bonus-history-table-wrap {
    overflow-x: auto;
}

.bonus-history-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 520px;
}

.bonus-history-table th,
.bonus-history-table td {
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 13px;
}

.bonus-history-table th {
    color: rgba(255, 255, 255, 0.62);
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.08em;
}

.bonus-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    inset: 0;
    background: rgba(0, 0, 0, 0.8);
    justify-content: center;
    align-items: center;
    padding: 16px;
}

.bonus-modal-content {
    width: min(100%, 420px);
    background: #1c1f2b;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 20px;
    color: #fff;
}

.bonus-modal-content h2 {
    margin: 0 0 8px;
    color: #fff;
}

.bonus-modal-content p {
    margin: 0;
    color: rgba(255, 255, 255, 0.82);
}

.bonus-modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 18px;
}

.btn-cancel,
.btn-confirm {
    flex: 1;
    border: 0;
    border-radius: 10px;
    padding: 10px 12px;
    font-weight: 700;
    cursor: pointer;
}

.btn-cancel {
    background: #4f5868;
    color: #fff;
}

.btn-confirm {
    background: linear-gradient(90deg, #ef5b2f, #f29a4b);
    color: #fff;
}

@media (max-width: 1100px) {
    .bonus-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .bonus-claim-page {
        padding: 14px 0 28px;
    }

    .bonus-member-card {
        grid-template-columns: 1fr;
    }

    .bonus-claim-header {
        padding: 18px;
    }

    .bonus-claim-header h1 {
        font-size: 25px;
    }

    .bonus-list {
        grid-template-columns: 1fr;
    }

    .bonus-tabs {
        gap: 8px;
    }

    .bonus-tab {
        width: 100%;
        text-align: center;
    }

    .bonus-history-table {
        min-width: 100%;
    }

    .bonus-history-table th,
    .bonus-history-table td {
        padding: 8px;
        font-size: 12px;
    }
}
</style>

<?php include VIEW_PATH . '/partials/footer.php'; ?>