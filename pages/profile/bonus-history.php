<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}
include __DIR__ . '/database.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /login');
    exit;
}

$username = $_SESSION['username'];

$user = ProfileApiHelper::profileByUsername($username);
if ($user === []) {
    $user = ['id' => null, 'first_name' => '', 'surname' => ''];
}

$user_info = ['username' => $username, 'id' => $user['id'] ?? null, 'first_name' => $user['first_name'] ?? '', 'surname' => $user['surname'] ?? ''];
$initial = strtoupper(substr($username, 0, 2));
$profileActiveTab = 'bonus-history';
$unread_count = 0;
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content bonus-history-main">
        <div class="bonus-history-card" id="bonusClaimsRoot">
                <h1 class="bonus-history-title">BONUS GEÇMİŞİ</h1>
                <a href="/" class="bonus-history-close" aria-label="Kapat"><i class="fa-solid fa-times"></i></a>
            </div>
            <p class="bonus-claims-lead">Panel üzerinden ilettiğiniz bonus taleplerinin durumunu buradan takip edebilirsiniz.</p>

            <div class="bonus-history-filters bonus-claims-toolbar">
                <div class="bghf-group">
                    <label class="bghf-label" for="bonusClaimsLimit">LİSTE ADEDİ</label>
                    <select id="bonusClaimsLimit" class="bghf-select" aria-label="Döndürülecek kayıt sayısı">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div class="bghf-group bghf-actions">
                    <button type="button" id="bonusClaimsReload" class="bghf-btn-show">YENİLE</button>
                </div>
            </div>

            <div id="bonusClaimsStatus" class="bonus-claims-status" role="status" aria-live="polite"></div>

            <div class="bonus-history-content bonus-claims-content">
                <div id="bonusClaimsLoading" class="bonus-claims-loading is-hidden" hidden>Yükleniyor…</div>
                <p id="bonusClaimsEmpty" class="bonus-history-empty bonus-claims-empty is-hidden" hidden>Henüz bonus talebi bulunmuyor.</p>
                <div class="table-responsive bonus-claims-table-wrap is-hidden" id="bonusClaimsTableWrap" hidden>
                    <table class="bonus-history-table bonus-claims-table">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Bonus</th>
                                <th>Kategori</th>
                                <th>Talep</th>
                                <th>Çevrim</th>
                                <th>Durum</th>
                                <th>Not</th>
                            </tr>
                        </thead>
                        <tbody id="bonusClaimsTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
<?php if (!$profile_modal): ?>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
