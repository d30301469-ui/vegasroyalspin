<html>
<head>
<?php
if (defined('VIEW_PATH') && is_file(VIEW_PATH . '/partials/member-api-layout-script.php')) {
    include VIEW_PATH . '/partials/member-api-layout-script.php';
}
?>
<script src="/assets/js/auth-shared.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.js"></script>
    <script src="/assets/js/toastify-helper.js"></script>

    <meta charset="UTF-8">
    <title>Oto Bonus</title>
</head>  
<body>  
    <div id="app" data-v-app="">  
        <div class="container">  
            <div class="category">  
                <h1>Oto Bonus</h1>  
                <div class="category-list">  
                    <button class="active" onclick="showBonuses('yatirim')">Yatırım Bonusları</button>  
                    <button onclick="showBonuses('kayip')">Kayıp Bonusları</button>  
                    <button onclick="showBonuses('deneme')">Deneme Bonusları</button>  
                </div>  
                <div class="bonus-list" id="bonus-list">  
                    <p>Bonuslar yükleniyor...</p>
                </div>  
            </div>  

            <div class="logs">  
                <div class="container">  
                    <h1>Geçmiş Bonuslar</h1>  
                    <table class="table table-dark table-striped">  
                        <thead>  
                            <tr>  
                                <th scope="col">Bonus</th>  
                                <th scope="col">Tarih</th>  
                                <th scope="col">Durum</th>  
                            </tr>  
                        </thead>  
                        <tbody></tbody>  
                    </table>  
                </div>  
            </div>  
            <p class="copyrigth">Developed by <a href="/" target="_blank">kupavip.com</a></p>  
        </div>  
    </div>  

    <!-- ✅ Modern Modal Tasarımı -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Bonus Onayı</h2>
            <p id="modalText">Bu bonusu almak istediğinizden emin misiniz?</p>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal()">Vazgeç</button>
                <button class="btn-confirm" id="confirmBonusBtn">Onayla</button>
            </div>
        </div>
    </div>

<script>  
let selectedBonus = null;
let selectedPromotionId = 0;
let activeBonusType = 'yatirim';
let promotions = [];

function openModal(bonusTuru, promotionId) {
    selectedBonus = bonusTuru;
    selectedPromotionId = parseInt(promotionId || 0, 10) || 0;

    document.getElementById('modalTitle').innerText = bonusTuru;
    document.getElementById('modalText').innerText = `${bonusTuru} talebinizi onaylıyor musunuz?`;
    document.getElementById('confirmModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
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
            MaltabetToast.success(data.message || 'Bonus başarıyla verildi!', 'Başarılı');
        } else {
            MaltabetToast.error(data.message || 'Bonus verilemedi.', 'Hata');
        }
    })  
    .catch(error => {  
        MaltabetToast.error('Bir hata oluştu. Lütfen tekrar deneyin.', 'Sunucu Hatası');
    });  
}

function promotionMatchesType(promo, type) {
    const text = `${promo.title || ''} ${promo.type || ''} ${promo.category || ''}`.toLocaleLowerCase('tr-TR');
    if (type === 'kayip') return text.includes('kayıp') || text.includes('kayip') || text.includes('iade');
    if (type === 'deneme') return text.includes('deneme') || text.includes('free') || text.includes('freespin') || text.includes('freebet');
    return text.includes('yatırım') || text.includes('yatirim') || text.includes('casino') || text.includes('slot') || text.includes('pragmatic') || (!promotionMatchesType(promo, 'kayip') && !promotionMatchesType(promo, 'deneme'));
}

function renderBonuses(type) {
    const bonusList = document.getElementById("bonus-list");
    const filtered = promotions.filter(promo => promotionMatchesType(promo, type));
    if (!filtered.length) {
        bonusList.innerHTML = '<p>Bu kategoride aktif bonus bulunmuyor.</p>';
        return;
    }
    bonusList.innerHTML = '';
    filtered.forEach(promo => {
        const title = String(promo.title || 'Bonus');
        const id = parseInt(promo.id || promo.promotionId || 0, 10) || 0;
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = title;
        button.addEventListener('click', () => openModal(title, id));
        bonusList.appendChild(button);
    });
}

function loadPromotions() {
    fetch(memberApiUrl('/api/v2/content/promotions'), {
        credentials: memberCredentials(),
        headers: { 'Accept': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        promotions = data && data.success && data.data && Array.isArray(data.data.promotions)
            ? data.data.promotions
            : [];
        renderBonuses(activeBonusType);
    })
    .catch(() => {
        document.getElementById("bonus-list").innerHTML = '<p>Bonuslar yüklenemedi.</p>';
    });
}

function showBonuses(type) {  
    activeBonusType = type;
    const bonusList = document.getElementById("bonus-list");  
    const buttons = document.querySelectorAll(".category-list button");  
    buttons.forEach(button => button.classList.remove("active"));  

    if (type === 'yatirim') {  
        buttons[0].classList.add("active");  
    } else if (type === 'kayip') {  
        buttons[1].classList.add("active");  
    } else if (type === 'deneme') {  
        buttons[2].classList.add("active");  
    }  
    renderBonuses(type);
}

loadPromotions();
</script>  

<style>  
body {
    background-color: #0c0c0c;
    color: white;
    font-family: var(--font-sans);
    margin: 0;
    text-align: center;
}

.container {
    margin: 5rem auto;
    max-width: 600px;
}

.category-list button {
    background-color: #181818;
    color: white;
    border: 1px solid #ccc;
    border-radius: 8px;
    padding: 0.6rem 1.2rem;
    cursor: pointer;
    transition: 0.3s;
}

.category-list button.active {
    background-color: white;
    color: black;
}

.bonus-list {
    margin-top: 1rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
}

.bonus-list button {
    width: 48%;
    background-color: #950101;
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 1rem;
    padding: 1rem;
    cursor: pointer;
    transition: 0.3s;
}

.bonus-list button:hover {
    opacity: 0.8;
}

.logs {
    margin-top: 3rem;
}

.copyrigth {
    margin-top: 2rem;
    font-size: 0.8rem;
    color: #bbb;
}

/* ✅ Modal Stil */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    inset: 0;
    background: rgba(0, 0, 0, 0.8);
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background: #1c1c1c;
    padding: 2rem;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    color: #fff;
    text-align: center;
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.1);
}

.modal-content h2 {
    margin-bottom: 1rem;
    color: #ff4747;
}

.modal-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 1.5rem;
}

.btn-cancel,
.btn-confirm {
    flex: 1;
    margin: 0 5px;
    padding: 0.8rem;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.btn-cancel {
    background: #555;
    color: #fff;
}

.btn-confirm {
    background: #ff4747;
    color: white;
}

.btn-cancel:hover {
    background: #777;
}

.btn-confirm:hover {
    background: #e63b3b;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>  
</body>  
</html>