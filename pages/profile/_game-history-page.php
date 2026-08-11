<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /login');
    exit;
}

$username = (string) ($_SESSION['username'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;
$initial = strtoupper(substr($username, 0, 2));
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';

$requestedSource = strtolower(trim((string) ($_GET['source'] ?? $_GET['type'] ?? '')));
$allowedSources = ['all', 'slot', 'live_casino'];
if ($requestedSource === 'live' || $requestedSource === 'livecasino') {
    $requestedSource = 'live_casino';
}
$gameHistorySource = $gameHistorySource ?? ($requestedSource !== '' ? $requestedSource : 'all');
if (!in_array($gameHistorySource, $allowedSources, true)) {
    $gameHistorySource = 'all';
}
$gameHistoryTitle = $gameHistoryTitle ?? 'CASINO OYUN GEÇMİŞİ';
$profileActiveTab = $profileActiveTab ?? 'casino-history';
$user_info = [
    'username' => $username,
    'id' => $user_id,
    'first_name' => $_SESSION['first_name'] ?? '',
    'surname' => $_SESSION['surname'] ?? '',
];

$filterLabels = [
    'all' => 'TÜMÜ',
    'slot' => 'SLOT',
    'live_casino' => 'CANLI CASİNO',
];
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content bet-history-main">
        <?php
        $profile_content_title = $gameHistoryTitle;
        $profile_content_page_class = 'personal-details-page--bet-history personal-details-page--game-history';
        $profile_close_href_full = '/profile/details';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>

        <div class="bet-history-content casino-history-root"
             data-casino-history-root
             data-source="<?= htmlspecialchars($gameHistorySource, ENT_QUOTES, 'UTF-8') ?>"
             data-api="/api/v2/profile/casino-game-history">
            <div class="bet-history-filters casino-history-filters" aria-label="Casino oyun geçmişi filtresi">
                <?php
                $cm622_filter_form_id = 'casinoHistoryFilterForm';
                $cm622_filter_form_class = '';
                $cm622_filter_no_form = true;
                $cm622_filter_title = 'FİLTRE';
                include __DIR__ . '/../../views/partials/cm622-filter-shell-open.php';
                ?>
                <div class="u-i-p-control-item-holder-bc">
                    <?php
                    $ms_id = 'casinoHistorySourceMs';
                    $ms_input_id = 'casinoHistorySourceFilter';
                    $ms_name = '';
                    $ms_title = 'Kategori';
                    $ms_selected = $gameHistorySource;
                    $ms_options = $filterLabels;
                    include __DIR__ . '/../../views/partials/cm622-multi-select.php';
                    ?>
                </div>
                <div class="u-i-p-control-item-holder-bc">
                    <?php
                    $ms_id = 'casinoHistoryTxnMs';
                    $ms_input_id = 'casinoHistoryTxnFilter';
                    $ms_name = '';
                    $ms_title = 'İşlem';
                    $ms_selected = 'all';
                    $ms_options = [
                        'all' => 'TÜMÜ',
                        'bet' => 'BAHİS',
                        'win' => 'KAZANÇ',
                        'refund' => 'İADE',
                        'adjustment' => 'DÜZELTME',
                    ];
                    include __DIR__ . '/../../views/partials/cm622-multi-select.php';
                    ?>
                </div>
                <?php
                $fc_id = 'casinoHistoryProviderFilter';
                $fc_name = '';
                $fc_title = 'Sağlayıcı';
                $fc_value = '';
                $fc_search_icon = true;
                $fc_attrs = 'placeholder=""';
                include __DIR__ . '/../../views/partials/cm622-form-control-text.php';

                $cm622_filter_submit_label = 'Göster';
                $cm622_filter_submit_id = 'casinoHistoryApplyBtn';
                $cm622_filter_submit_type = 'button';
                $cm622_filter_no_form = true;
                include __DIR__ . '/../../views/partials/cm622-filter-shell-close.php';
                ?>
            </div>
            <p class="bet-history-empty" data-casino-history-empty hidden>Gösterilecek oyun geçmişi yok</p>
            <div class="table-responsive u-i-page-table" data-casino-history-table-wrap>
                <table class="bet-history-table" id="casinoGameHistoryTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Oyun</th>
                            <th>Sağlayıcı</th>
                            <th>Kategori</th>
                            <th>İşlem</th>
                            <th>Bahis</th>
                            <th>Kazanç</th>
                            <th>Bakiye</th>
                            <th>Detay</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody id="casinoGameHistoryTableBody" data-casino-history-body>
                        <tr><td colspan="10">Oyun geçmişi yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

<?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php if (!$profile_modal): ?>
</div>
<?php endif; ?>

<div class="modal fade" id="gameHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Oyun Geçmişi Detayları</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="gameHistoryContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
