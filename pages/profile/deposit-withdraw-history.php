<?php

require_once __DIR__ . '/database.php';

$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host !== '' && str_starts_with($host, 'm.') && !$profile_modal) {
    header('Location: /?' . http_build_query(['profile' => 'open', 'account' => 'balance', 'page' => 'history']));
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
                <?php
                $cm622_filter_form_id = 'depositHistoryFilterForm';
                $cm622_filter_no_form = true;
                $cm622_filter_title = 'FİLTRE';
                include __DIR__ . '/../../views/partials/cm622-filter-shell-open.php';
                ?>
                <div class="u-i-p-control-item-holder-bc">
                    <?php
                    $ms_id = 'depositHistoryTypeMs';
                    $ms_input_id = 'depositHistoryTypeFilter';
                    $ms_name = '';
                    $ms_title = 'İşlem Türü';
                    $ms_selected = 'deposit';
                    $ms_options = [
                        'deposit' => 'Yatırım',
                        'withdraw' => 'Çekim',
                    ];
                    include __DIR__ . '/../../views/partials/cm622-multi-select.php';
                    ?>
                </div>
                <div class="u-i-p-control-item-holder-bc">
                    <?php
                    $ms_id = 'depositHistoryStatusMs';
                    $ms_input_id = 'depositHistoryStatusFilter';
                    $ms_name = '';
                    $ms_title = 'Durum';
                    $ms_selected = '';
                    $ms_options = [
                        '' => 'Tümü',
                        'pending' => 'Beklemede',
                        'processing' => 'İşleniyor',
                        'approved' => 'Onaylandı',
                        'confirmed' => 'Onaylandı',
                        'completed' => 'Tamamlandı',
                        'rejected' => 'Reddedildi',
                        'failed' => 'Başarısız',
                    ];
                    include __DIR__ . '/../../views/partials/cm622-multi-select.php';
                    ?>
                </div>
                <?php
                $cm622_filter_submit_label = 'Göster';
                $cm622_filter_submit_id = 'depositHistoryApplyBtn';
                $cm622_filter_submit_type = 'button';
                $cm622_filter_no_form = true;
                include __DIR__ . '/../../views/partials/cm622-filter-shell-close.php';
                ?>
            </div>

            <div class="tx-history-content u-i-page-content">
                <div id="txHistoryEmpty" class="tx-history-empty" style="display: none;">
                    Kayıt bulunamadı
                </div>
                <div id="txHistoryError" class="tx-history-empty tx-history-cell-err" style="display: none;"></div>
                <div id="txHistoryTableWrap" class="tx-history-table-wrap u-i-page-table">
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
