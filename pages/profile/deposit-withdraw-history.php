<?php

require_once __DIR__ . '/database.php';

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host !== '' && str_starts_with($host, 'm.')) {
    header('Location: /mobile/profile?' . http_build_query(['profile' => 'open', 'account' => 'balance', 'page' => 'deposit', 'openDepositPanel' => '1']));
    exit();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /');
    exit();
}

$username = $_SESSION['username'];

$prof      = ProfileApiHelper::profileByUsernameCached($username);
$user_info = array_merge(
    ['username' => $username],
    [
        'id'         => $prof['id'] ?? null,
        'first_name' => $prof['first_name'] ?? '',
        'surname'    => $prof['surname'] ?? '',
    ]
);
$initial = strtoupper(substr($username, 0, 2));
$profileActiveTab = 'deposit-withdraw-history';
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php endif; ?>
<script>
window.__DEPOSIT_HISTORY_API__ = true;
window.__DEPOSIT_HISTORY_ENDPOINT__ = '/api/v2/deposit-history';
window.__WITHDRAW_HISTORY_ENDPOINT__ = '/api/v2/withdraw-history';
</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">

<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content tx-history-main">
        <?php
        $profile_content_title = 'İŞLEM GEÇMİŞİ';
        $profile_content_page_class = 'personal-details-page--tx-history';
        $profile_close_href_full = '/profile/details';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>

            <div class="tx-history-filters" role="search" aria-label="İşlem geçmişi filtreleri">
                <div class="tx-filter-toolbar">
                    <div class="tx-filter-field">
                        <label class="tx-filter-label" for="depositHistoryTypeFilter">İşlem Türü</label>
                        <select class="tx-filter-select" id="depositHistoryTypeFilter">
                            <option value="deposit" selected>Yatırım</option>
                            <option value="withdraw">Çekim</option>
                        </select>
                    </div>
                    <div class="tx-filter-field">
                        <label class="tx-filter-label" for="depositHistoryStatusFilter">Durum</label>
                        <select class="tx-filter-select" id="depositHistoryStatusFilter">
                            <option value="">Tümü</option>
                            <option value="pending">Beklemede</option>
                            <option value="processing">İşleniyor</option>
                            <option value="approved">Onaylandı</option>
                            <option value="confirmed">Onaylandı</option>
                            <option value="completed">Tamamlandı</option>
                            <option value="rejected">Reddedildi</option>
                            <option value="failed">Başarısız</option>
                        </select>
                    </div>
                    <button type="button" class="tx-filter-btn-show" id="depositHistoryApplyBtn" title="Listeyi yenile">Göster</button>
                </div>
            </div>

            <div class="tx-history-content">
                <div id="txHistoryEmpty" class="tx-history-empty" style="display: none;">
                    Kayıt bulunamadı
                </div>
                <div id="txHistoryError" class="tx-history-empty tx-history-cell-err" style="display: none;"></div>
                <div id="txHistoryTableWrap" class="tx-history-table-wrap">
                    <table class="tx-history-table" id="transactionTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Yöntem</th>
                                <th>Sağlayıcı</th>
                                <th>Referans</th>
                                <th>Tutar</th>
                                <th>Ücret</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody id="transactionTableBody">
                            <tr><td colspan="8" class="tx-history-cell-center">Yükleniyor…</td></tr>
                        </tbody>
                    </table>
                </div>
                <nav class="tx-history-pagination" id="depositHistoryPagination" aria-label="Sayfalama" style="display: none;"></nav>
            </div>
<?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php if (!$profile_modal): ?>
</div>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
