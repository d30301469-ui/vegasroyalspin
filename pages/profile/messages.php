<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';
include __DIR__ . '/database.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /login');
    exit;
}

$csrfKey = 'vegasroyalspin_csrf_token';
if (empty($_SESSION[$csrfKey]) || !is_string($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : bin2hex(random_bytes(32));
}
$_SESSION['csrf_token'] = $_SESSION[$csrfKey];

$username = $_SESSION['username'];

$user = ProfileApiHelper::profileByUsernameCached($username);
if ($user === []) {
    $user = ['id' => null, 'first_name' => '', 'surname' => ''];
}

$box = $_GET['box'] ?? 'inbox';
if (!in_array($box, ['inbox', 'sent', 'new'], true)) {
    $box = 'inbox';
}

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to   = $_GET['date_to'] ?? date('Y-m-d');

$user_info = ['username' => $username, 'id' => $user['id'] ?? null, 'first_name' => $user['first_name'] ?? '', 'surname' => $user['surname'] ?? ''];
$initial = strtoupper(substr($username, 0, 2));
$profileActiveTab = 'messages';
$messages_box = $box;
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';

$currentPageUrl = '/profile/messages?box=new' . ($profile_modal ? '&modal=1' : '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $box === 'new') {
    $isAjaxRequest = ((string) ($_POST['ajax'] ?? '') === '1')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $csrf = (string) ($_POST['csrf_token'] ?? '');

    $flashType = 'error';
    $flashMessage = 'Mesaj gönderilemedi.';
    $textLength = static function (string $value): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    };
    $fieldValues = [
        'subject' => $subject,
        'body' => $body,
    ];

    if ($csrf === '' || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        $flashMessage = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    } elseif ($subject === '' || $body === '') {
        $flashMessage = 'Konu ve mesaj alanları zorunludur.';
    } elseif ($textLength($subject) > 190) {
        $flashMessage = 'Konu en fazla 190 karakter olabilir.';
    } elseif ($textLength($body) > 5000) {
        $flashMessage = 'Mesaj en fazla 5000 karakter olabilir.';
    } else {
        try {
            $apiResponse = ProfileApiHelper::postProfile('/support/tickets', [
                'subject' => $subject,
                'body' => $body,
                'category' => 'profile_messages',
                'priority' => 'normal',
            ]);
            $isSuccess = is_array($apiResponse) && !empty($apiResponse['success']);
            if ($isSuccess) {
                $flashType = 'success';
                $flashMessage = (string) ($apiResponse['message'] ?? 'Mesajınız admine iletildi.');
                $fieldValues = ['subject' => '', 'body' => ''];
            } else {
                $flashMessage = (string) ($apiResponse['message'] ?? 'Mesaj gönderilemedi. Lütfen tekrar deneyin.');
            }
        } catch (Throwable) {
            $flashMessage = 'Mesaj gönderimi sırasında beklenmeyen bir hata oluştu.';
        }
    }

    if ($isAjaxRequest) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $flashType === 'success',
            'message' => $flashMessage,
            'values' => $fieldValues,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $_SESSION['profile_messages_flash'] = [
        'type' => $flashType,
        'message' => $flashMessage,
    ];
    $_SESSION['profile_messages_form'] = $fieldValues;

    header('Location: ' . $currentPageUrl, true, 303);
    exit;
}

$messagesFlash = $_SESSION['profile_messages_flash'] ?? null;
unset($_SESSION['profile_messages_flash']);
$messagesForm = $_SESSION['profile_messages_form'] ?? ['subject' => '', 'body' => ''];
unset($_SESSION['profile_messages_form']);

$profile_titles = [
    'inbox' => 'GELEN KUTUSU',
    'sent' => 'GÖNDERİLDİ',
    'new' => 'YENİ',
];
$profile_content_title = $profile_titles[$box];
$profile_content_page_class = 'personal-details-page--messages';
if ($box === 'sent') {
    $profile_content_page_class .= ' personal-details-page--messages-sent';
} elseif ($box === 'new') {
    $profile_content_page_class .= ' personal-details-page--messages-new';
}
$profile_close_href_full = '/profile/details';

// Gönderilen mesajlar (box=sent için) — ayrı uç yok; yerel örnek
$sent_messages = [
    ['id' => 1, 'subject' => 'merhaba', 'date' => '2026-03-19 03:05'],
];

$inbox_messages = [];
if ($box === 'inbox') {
    $raw_list = ApiMemberInboxMessages::fetchMessages();
    $ts_from  = strtotime($date_from . ' 00:00:00');
    $ts_to    = strtotime($date_to . ' 23:59:59');
    foreach ($raw_list as $row) {
        if (!is_array($row)) {
            continue;
        }
        $created_raw = isset($row['created_at']) ? (string) $row['created_at'] : '';
        $created_ts  = $created_raw !== '' ? strtotime($created_raw) : false;
        if ($created_ts === false) {
            continue;
        }
        if ($created_ts < $ts_from || $created_ts > $ts_to) {
            continue;
        }
        $link = $row['link_url'] ?? null;
        $inbox_messages[] = [
            'id'         => (int) ($row['id'] ?? 0),
            'title'      => (string) ($row['title'] ?? ''),
            'body'       => (string) ($row['body'] ?? ''),
            'link_url'   => ($link !== null && $link !== '') ? (string) $link : null,
            'priority'   => (int) ($row['priority'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}

$new_msg_action = $currentPageUrl;
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>

<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content">
        <?php
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>
        <div class="profile-messages-body">
            <?php if ($box === 'new'): ?>
            <form method="post" action="<?= htmlspecialchars($new_msg_action, ENT_QUOTES, 'UTF-8') ?>" class="new-msg-form" id="newMessageForm">
                <?php if (is_array($messagesFlash) && !empty($messagesFlash['message'])): ?>
                <div class="pm-alert pm-alert--<?= htmlspecialchars((string) ($messagesFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?>" role="status" aria-live="polite">
                    <?= htmlspecialchars((string) $messagesFlash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>
                <div class="pm-alert pm-alert--info js-new-message-feedback" role="status" aria-live="polite" hidden></div>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="new-msg-field">
                    <input type="text" name="subject" class="new-msg-input new-msg-subject" placeholder="Konu *" required maxlength="190" value="<?= htmlspecialchars((string) ($messagesForm['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="new-msg-field">
                    <label class="new-msg-label" for="newMessageBody">MESAJ *</label>
                    <textarea id="newMessageBody" name="body" class="new-msg-textarea" placeholder="Buraya metin giriniz" required rows="10" maxlength="5000"><?= htmlspecialchars((string) ($messagesForm['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="new-msg-footer">
                    <button type="submit" class="new-msg-btn-send">GÖNDER</button>
                </div>
            </form>
            <?php elseif ($box !== 'sent'): ?>
            <form method="get" action="/profile/messages" class="inbox-filters" id="messagesInboxFilterForm">
                <input type="hidden" name="box" value="inbox">
                <?php if ($profile_modal): ?><input type="hidden" name="modal" value="1"><?php endif; ?>
                <div class="inbox-filter-group">
                    <label class="inbox-filter-label" for="msgDateFrom">Tarihinden *</label>
                    <div class="inbox-date-wrap">
                        <input type="date" id="msgDateFrom" name="date_from" class="inbox-date-input" value="<?= htmlspecialchars($date_from) ?>">
                        <i class="fa-solid fa-calendar-days inbox-date-icon" aria-hidden="true"></i>
                    </div>
                </div>
                <div class="inbox-filter-group">
                    <label class="inbox-filter-label" for="msgDateTo">Tarihine *</label>
                    <div class="inbox-date-wrap">
                        <input type="date" id="msgDateTo" name="date_to" class="inbox-date-input" value="<?= htmlspecialchars($date_to) ?>">
                        <i class="fa-solid fa-calendar-days inbox-date-icon" aria-hidden="true"></i>
                    </div>
                </div>
                <div class="inbox-filter-actions">
                    <button type="submit" class="inbox-btn-show">GÖSTER</button>
                </div>
            </form>
            <?php endif; ?>

            <?php if ($box !== 'new'): ?>
            <div class="inbox-list-wrap<?= $box === 'inbox' && $inbox_messages === [] ? ' is-empty' : '' ?>">
                <?php if ($box === 'sent'): ?>
                <ul class="inbox-list inbox-list--sent">
                    <?php foreach ($sent_messages as $msg): ?>
                    <li class="sent-item">
                        <div class="sent-item-top">
                            <span class="sent-item-subject"><?= htmlspecialchars($msg['subject']) ?></span>
                            <button type="button" class="sent-item-delete" aria-label="Sil"><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                        <div class="sent-item-bottom">
                            <span class="sent-item-date"><?= date('d.m.Y, H:i', strtotime($msg['date'])) ?></span>
                            <button type="button" class="sent-item-expand" aria-label="Genişlet"><i class="fa-solid fa-chevron-down"></i></button>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <?php if ($inbox_messages === []): ?>
                <p class="inbox-empty-hint">Bu tarih aralığında mesaj yok.</p>
                <?php else: ?>
                <ul class="inbox-list">
                    <?php foreach ($inbox_messages as $msg): ?>
                    <li class="inbox-item js-inbox-item" data-inbox-id="<?= (int) $msg['id'] ?>" data-inbox-updated="<?= htmlspecialchars($msg['updated_at'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="inbox-item-inner">
                            <span class="inbox-item-dot" aria-hidden="true"></span>
                            <div class="inbox-item-top">
                                <i class="fa-solid fa-envelope-open-text inbox-item-icon" aria-hidden="true"></i>
                                <span class="inbox-item-title"><?= htmlspecialchars($msg['title']) ?></span>
                            </div>
                            <div class="inbox-item-bottom">
                                <span class="inbox-item-date"><?= $msg['created_at'] !== '' ? date('d.m.Y, H:i', strtotime($msg['created_at'])) : '—' ?></span>
                                <button type="button" class="inbox-item-expand" aria-expanded="false" aria-label="İçeriği göster"><i class="fa-solid fa-chevrons-down" aria-hidden="true"></i></button>
                            </div>
                            <div class="inbox-item-body" hidden>
                                <div class="inbox-item-bodyrichtext"><?= $msg['body'] ?></div>
                                <?php if (!empty($msg['link_url'])): ?>
                                <p class="inbox-item-link-wrap"><a href="<?= htmlspecialchars($msg['link_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Bağlantıya git</a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="inbox-footer">
                <a href="<?= htmlspecialchars('/profile/messages?box=new' . ($profile_modal ? '&modal=1' : ''), ENT_QUOTES, 'UTF-8') ?>" class="inbox-btn-new <?= $box === 'sent' ? 'inbox-btn-new--sent' : '' ?>">YENİ MESAJ</a>
            </div>
            <?php endif; ?>
        </div>
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php if (!$profile_modal): ?>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
