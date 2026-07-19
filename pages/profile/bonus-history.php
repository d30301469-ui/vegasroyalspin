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

$user = ProfileApiHelper::profileByUsernameCached($username);
if ($user === []) {
    $user = ['id' => null, 'first_name' => '', 'surname' => ''];
}

$user_info = ['username' => $username, 'id' => $user['id'] ?? null, 'first_name' => $user['first_name'] ?? '', 'surname' => $user['surname'] ?? ''];
$initial = strtoupper(substr($username, 0, 2));
$profileActiveTab = 'bonus-history';
$unread_count = 0;
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
$profile_content_title = 'BONUS GEÇMİŞİ';
$profile_content_page_class = 'personal-details-page--loyalty-points personal-details-page--bonus-unified personal-details-page--bonus-history-unified';
$profile_close_href_full = '/profile/details';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content">
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-open.php'; ?>
        <div class="bonus-history-card bonus-unified-card" id="bonusClaimsRoot">
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
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php if (!$profile_modal): ?>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
