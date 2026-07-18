<?php

declare(strict_types=1);

final class AdminPromotionController extends AdminController
{
    private static bool $promotionSchemaEnsured = false;

    /** @var array<string, string> Frontend kategori filtresiyle uyumlu değerler (ApiPromotions::CATEGORY_SLUGS). */
    private const CATEGORY_OPTIONS = [
        'sports' => 'Spor',
        'live_casino' => 'Canlı Casino',
        'slots' => 'Slot',
        'loss_bonus' => 'Kayıp Bonusu',
        'vip' => 'VIP',
    ];

    public function index(): void
    {
        $this->requirePermission('promotions');
        self::ensurePromotionSchema();
        $pdo = AdminDatabase::pdo();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        $search = trim((string) ($_GET['search'] ?? ''));
        $where = '';
        $bind = [];
        if ($search !== '') {
            $where = 'WHERE title LIKE :search OR type LIKE :search OR category LIKE :search';
            $bind['search'] = '%' . $search . '%';
        }

        try {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM promotions $where");
            $countStmt->execute($bind);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT * FROM promotions $where ORDER BY sort_order ASC, id DESC LIMIT :limit OFFSET :offset");
            foreach ($bind as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $promotions = $stmt->fetchAll();
            if (is_array($promotions)) {
                foreach ($promotions as &$promotionRow) {
                    if (!is_array($promotionRow)) {
                        continue;
                    }
                    $promotionRow['image_url'] = class_exists('PromotionMediaGuard', false)
                        ? PromotionMediaGuard::resolveDisplayImageUrl(
                            (string) ($promotionRow['image_url'] ?? ''),
                            (string) ($promotionRow['title'] ?? '')
                        )
                        : self::normalizeDisplayImageUrl((string) ($promotionRow['image_url'] ?? ''));
                }
                unset($promotionRow);
            }
        } catch (Throwable) {
            $promotions = [];
            $total = 0;
        }

        $this->view('promotions/index', [
            'title' => 'Promosyonlar',
            'active' => 'promotions',
            'crumbs' => 'Marketing | Promotions',
            'promotions' => $promotions,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
            'search' => $search,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('promotions');
        self::ensurePromotionSchema();
        $this->view('promotions/form', [
            'title' => 'Promosyon Ekle',
            'active' => 'promotions',
            'crumbs' => 'Marketing | Promotions | Ekle',
            'promotion' => [],
            'mode' => 'create',
            'categoryOptions' => self::CATEGORY_OPTIONS,
            'libraryImages' => PromotionMediaGuard::listLibraryImages(),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('promotions');
        $this->ensurePost();
        self::ensurePromotionSchema();

        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $this->flash('Başlık zorunludur.');
            $this->redirect(AdminAuth::url('/promotion/create'));
        }

        try {
            $uploadedImageUrl = self::handleImageUpload($_FILES['image_file'] ?? null);
            $imageUrl = $uploadedImageUrl ?? self::normalizePromotionImageUrl((string) ($_POST['image_url'] ?? ''));
            $linkUrl = self::normalizePromotionLinkUrl((string) ($_POST['link_url'] ?? ''));
            AdminDatabase::pdo()->prepare(
                'INSERT INTO promotions (title, description, type, category, status, sort_order, image_url, link_url, bonus_amount, wagering_multiplier, created_at, updated_at)
                 VALUES (:title, :description, :type, :category, :status, :sort_order, :image_url, :link_url, :bonus_amount, :wagering_multiplier, NOW(), NOW())'
            )->execute([
                'title' => $title,
                'description' => trim((string) ($_POST['description'] ?? '')),
                'type' => trim((string) ($_POST['type'] ?? '')),
                'category' => trim((string) ($_POST['category'] ?? '')),
                'status' => trim((string) ($_POST['status'] ?? 'active')),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'image_url' => $imageUrl,
                'link_url' => $linkUrl,
                'bonus_amount' => (float) ($_POST['bonus_amount'] ?? 0),
                'wagering_multiplier' => (float) ($_POST['wagering_multiplier'] ?? 0),
            ]);
            AdminAuth::writeLog(AdminAuth::userName(), 'promotion_create', 'promotions', 'success');
            $this->flash('Promosyon eklendi.');
        } catch (Throwable $exception) {
            $this->flash('Promosyon eklenemedi: ' . $exception->getMessage());
        }

        $this->redirect(AdminAuth::url('/promotions'));
    }

    public function edit(): void
    {
        $this->requirePermission('promotions');
        self::ensurePromotionSchema();
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $promotion = $this->findPromotion($id);
        if ($promotion === null) {
            $this->flash('Promosyon bulunamadı.');
            $this->redirect(AdminAuth::url('/promotions'));
        }

        $data = [
            'title' => 'Promosyon Düzenle',
            'active' => 'promotions',
            'crumbs' => 'Marketing | Promotions | Düzenle',
            'promotion' => array_merge($promotion, [
                'image_url' => class_exists('PromotionMediaGuard', false)
                    ? PromotionMediaGuard::resolveDisplayImageUrl(
                        (string) ($promotion['image_url'] ?? ''),
                        (string) ($promotion['title'] ?? '')
                    )
                    : self::normalizeDisplayImageUrl((string) ($promotion['image_url'] ?? '')),
            ]),
            'mode' => 'edit',
            'categoryOptions' => self::CATEGORY_OPTIONS,
            'libraryImages' => PromotionMediaGuard::listLibraryImages(),
            'flash' => $this->pullFlash(),
        ];

        if ($this->isModalRequest()) {
            $data['isModal'] = true;
            $this->partial('promotions/_form', $data);
            return;
        }

        $this->view('promotions/form', $data);
    }

    public function update(): void
    {
        $this->requirePermission('promotions');
        $this->ensurePost();
        self::ensurePromotionSchema();

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($id <= 0) {
            $this->flash('Geçersiz promosyon ID.');
            $this->redirect(AdminAuth::url('/promotions'));
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $this->flash('Başlık zorunludur.');
            $this->redirect(AdminAuth::url('/promotion/edit?id=' . rawurlencode((string) $id)));
        }

        try {
            $uploadedImageUrl = self::handleImageUpload($_FILES['image_file'] ?? null);
            $imageUrl = $uploadedImageUrl ?? self::normalizePromotionImageUrl((string) ($_POST['image_url'] ?? ''));
            $linkUrl = self::normalizePromotionLinkUrl((string) ($_POST['link_url'] ?? ''));
            AdminDatabase::pdo()->prepare(
                'UPDATE promotions SET title = :title, description = :description, type = :type, category = :category,
                 status = :status, sort_order = :sort_order, image_url = :image_url, link_url = :link_url,
                 bonus_amount = :bonus_amount, wagering_multiplier = :wagering_multiplier, updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'title' => $title,
                'description' => trim((string) ($_POST['description'] ?? '')),
                'type' => trim((string) ($_POST['type'] ?? '')),
                'category' => trim((string) ($_POST['category'] ?? '')),
                'status' => trim((string) ($_POST['status'] ?? 'active')),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'image_url' => $imageUrl,
                'link_url' => $linkUrl,
                'bonus_amount' => (float) ($_POST['bonus_amount'] ?? 0),
                'wagering_multiplier' => (float) ($_POST['wagering_multiplier'] ?? 0),
                'id' => $id,
            ]);
            AdminAuth::writeLog(AdminAuth::userName(), 'promotion_update', 'promotions', 'success', (string) $id);
            $this->flash('Promosyon güncellendi.');
        } catch (Throwable $exception) {
            $this->flash('Promosyon güncellenemedi: ' . $exception->getMessage());
        }

        $this->redirect(AdminAuth::url('/promotions'));
    }

    private static function normalizePromotionImageUrl(string $imageUrl): string
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $imageUrl) === 1) {
            $host = strtolower((string) (parse_url($imageUrl, PHP_URL_HOST) ?? ''));
            if (preg_match('/^(?:icons|cms)\.casinomilyon\d+\.com$/i', $host) === 1) {
                return $imageUrl;
            }

            $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?? '');
            if ($path !== '') {
                $imageUrl = $path;
            }
        }

        $imageUrl = '/' . ltrim(str_replace('\\', '/', $imageUrl), '/');
        $lower = strtolower($imageUrl);

        if (str_starts_with($lower, '/storage/uploads/')) {
            return '/uploads/' . ltrim(substr($imageUrl, strlen('/storage/uploads/')), '/');
        }
        if (str_starts_with($lower, '/admin/uploads/')) {
            return '/uploads/' . ltrim(substr($imageUrl, strlen('/admin/uploads/')), '/');
        }

        return $imageUrl;
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
     */
    private static function handleImageUpload(?array $file): ?string
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return null;
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return null;
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowed, true)) {
            return null;
        }

        // Gerçek görsel içeriği doğrulanır (uzantı sahteciliğine karşı savunma).
        if (@getimagesize($tmpName) === false) {
            return null;
        }

        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__, 3);
        $targetDir = rtrim($base, '/\\') . '/admin/upload/bonuses';
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return null;
        }
        if (!is_writable($targetDir)) {
            return null;
        }

        $filename = 'promo_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destination = $targetDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            return null;
        }

        return '/upload/bonuses/' . $filename;
    }

    private static function normalizePromotionLinkUrl(string $linkUrl): string
    {
        $linkUrl = trim($linkUrl);
        if ($linkUrl === '') {
            return '';
        }

        if (preg_match('#^(?:javascript|data):#i', $linkUrl) === 1) {
            return '';
        }

        if (preg_match('#^https?://#i', $linkUrl) === 1) {
            return $linkUrl;
        }

        if (str_starts_with($linkUrl, '//')) {
            return 'https:' . $linkUrl;
        }

        $linkUrl = str_replace('\\', '/', $linkUrl);

        if ($linkUrl[0] === '/' || $linkUrl[0] === '?') {
            return $linkUrl;
        }

        return '/' . ltrim($linkUrl, '/');
    }

    private static function normalizeDisplayImageUrl(string $imageUrl): string
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return '';
        }

        $path = $imageUrl;
        if (preg_match('#^https?://#i', $path) === 1) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?? '');
        }
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with(strtolower($path), '/uploads/promotions/')) {
            $file = basename($path);
            if ($file !== '') {
                $source = (defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__, 2)) . '/upload/bonuses/' . $file;
                if (is_file($source)) {
                    return '/upload/bonuses/' . $file;
                }
            }
        }

        return $imageUrl;
    }

    public function delete(): void
    {
        $this->requirePermission('promotions');
        $this->ensurePost();
        self::ensurePromotionSchema();

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($id <= 0) {
            $this->flash('Geçersiz promosyon ID.');
            $this->redirect(AdminAuth::url('/promotions'));
        }

        try {
            AdminDatabase::pdo()->prepare('DELETE FROM promotions WHERE id = :id')->execute(['id' => $id]);
            AdminAuth::writeLog(AdminAuth::userName(), 'promotion_delete', 'promotions', 'success', (string) $id);
            $this->flash('Promosyon silindi.');
        } catch (Throwable $exception) {
            $this->flash('Promosyon silinemedi: ' . $exception->getMessage());
        }

        $this->redirect(AdminAuth::url('/promotions'));
    }

    public function claims(): void
    {
        $this->requirePermission('promotions');
        self::ensurePromotionSchema();
        $promoId = max(0, (int) ($_GET['id'] ?? 0));
        $promotion = $this->findPromotion($promoId);
        if ($promotion === null) {
            $this->flash('Promosyon bulunamadı.');
            $this->redirect(AdminAuth::url('/promotions'));
        }

        $pdo = AdminDatabase::pdo();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        $status = trim((string) ($_GET['status'] ?? ''));
        $where = ['b.promotion_id = :pid'];
        $bind = ['pid' => $promoId];
        if ($status !== '') {
            $where[] = 'b.status = :status';
            $bind['status'] = $status;
        }
        $whereSQL = implode(' AND ', $where);

        try {
            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM user_active_bonuses b WHERE $whereSQL");
            $cntStmt->execute($bind);
            $total = (int) $cntStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT b.id, b.user_id, b.name, b.status,
                       b.initial_amount, b.current_bonus_balance,
                       b.wagering_requirement, b.total_bet_amount,
                       b.granted_at, b.deadline, b.completed_at,
                       u.username, u.email
                FROM user_active_bonuses b
                LEFT JOIN users u ON u.id = b.user_id
                WHERE $whereSQL
                ORDER BY b.id DESC
                LIMIT :limit OFFSET :offset
            ");
            foreach ($bind as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $claims = $stmt->fetchAll();
        } catch (Throwable) {
            $claims = [];
            $total = 0;
        }

        $this->view('promotions/claims', [
            'title' => $promotion['title'] . ' — Talepler',
            'active' => 'promotions',
            'crumbs' => 'Marketing | Promotions | Claims',
            'promotion' => $promotion,
            'claims' => $claims,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
            'statusFilter' => $status,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function assignBonus(): void
    {
        $this->requirePermission('promotions');
        $this->ensurePost();
        self::ensurePromotionSchema();

        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        $promoId = max(0, (int) ($_POST['promotion_id'] ?? 0));
        $amount = (float) ($_POST['amount'] ?? 0);
        $wagering = max(1, (float) ($_POST['wagering_multiplier'] ?? 1));
        $bonusName = trim((string) ($_POST['name'] ?? 'Manuel Bonus'));

        if ($userId <= 0) {
            $this->flash('Kullanıcı ID zorunludur.');
            $this->redirect(AdminAuth::url('/promotions'));
        }
        if ($promoId <= 0 && $amount <= 0) {
            $this->flash('Promosyon veya tutar zorunludur.');
            $this->redirect(AdminAuth::url('/promotions'));
        }

        $pdo = AdminDatabase::pdo();
        $userChk = $pdo->prepare('SELECT id, username FROM users WHERE id = :id LIMIT 1');
        $userChk->execute(['id' => $userId]);
        if (!$userChk->fetch()) {
            $this->flash('Kullanıcı bulunamadı.');
            $this->redirect(AdminAuth::url('/promotions'));
        }

        $promo = null;
        if ($promoId > 0) {
            $s = $pdo->prepare("SELECT * FROM promotions WHERE id = :id AND status = 'active' LIMIT 1");
            $s->execute(['id' => $promoId]);
            $promo = $s->fetch() ?: null;
        }

        $bonusAmount = $promo ? (float) ($promo['bonus_amount'] ?? $amount) : $amount;
        $finalName = $promo ? (string) ($promo['title'] ?? $bonusName) : $bonusName;
        $wageringMult = $promo ? (float) ($promo['wagering_multiplier'] ?? $wagering) : $wagering;
        $wageringTarget = $bonusAmount * max(1, $wageringMult);
        $deadline = date('Y-m-d H:i:s', strtotime('+30 days'));

        try {
            $pdo->prepare(
                "INSERT INTO user_active_bonuses
                 (user_id, promotion_id, name, category, initial_amount, current_bonus_balance,
                  wagering_requirement, wagering_target, total_bet_amount, status, granted_at, deadline)
                 VALUES (:user_id, :promotion_id, :name, :category, :amount, :amount,
                  :wagering_req, :wagering_target, 0, 'active', NOW(), :deadline)"
            )->execute([
                'user_id' => $userId,
                'promotion_id' => $promoId > 0 ? $promoId : null,
                'name' => $finalName,
                'category' => $promo ? (string) ($promo['type'] ?? 'manual') : 'manual',
                'amount' => number_format($bonusAmount, 2, '.', ''),
                'wagering_req' => $wageringMult,
                'wagering_target' => number_format($wageringTarget, 2, '.', ''),
                'deadline' => $deadline,
            ]);
            AdminAuth::writeLog(AdminAuth::userName(), 'bonus_assign', 'users', 'success', (string) $userId);
            $this->flash("Bonus başarıyla atandı: $finalName ($bonusAmount TRY)");
        } catch (Throwable $exception) {
            $this->flash('Bonus ataması başarısız: ' . $exception->getMessage());
        }

        $redirectId = (string) ($_POST['redirect_user'] ?? '');
        if ($redirectId !== '' && is_numeric($redirectId)) {
            $this->redirect(AdminAuth::url('/user?id=' . rawurlencode($redirectId)));
        }
        $this->redirect(AdminAuth::url('/promotions'));
    }

    public function revokeBonus(): void
    {
        $this->requirePermission('promotions');
        $this->ensurePost();
        self::ensurePromotionSchema();

        $bonusId = max(0, (int) ($_POST['bonus_id'] ?? 0));
        $userId = max(0, (int) ($_POST['user_id'] ?? 0));

        if ($bonusId <= 0 && $userId <= 0) {
            $this->flash('bonus_id veya user_id zorunludur.');
            $this->redirect(AdminAuth::url('/promotions'));
        }

        $pdo = AdminDatabase::pdo();
        $where = $bonusId > 0 ? 'id = :id' : "user_id = :user_id AND status = 'active'";
        $bind = $bonusId > 0 ? ['id' => $bonusId] : ['user_id' => $userId];

        try {
            $stmt = $pdo->prepare("UPDATE user_active_bonuses SET status = 'revoked' WHERE $where");
            $stmt->execute($bind);
            $count = $stmt->rowCount();
            if ($count === 0) {
                $this->flash('Aktif bonus bulunamadı.');
            } else {
                AdminAuth::writeLog(AdminAuth::userName(), 'bonus_revoke', 'users', 'success', (string) ($bonusId ?: $userId));
                $this->flash("$count bonus iptal edildi.");
            }
        } catch (Throwable $exception) {
            $this->flash('Bonus iptal edilemedi: ' . $exception->getMessage());
        }

        $redirectUser = (string) ($_POST['redirect_user'] ?? '');
        if ($redirectUser !== '' && is_numeric($redirectUser)) {
            $this->redirect(AdminAuth::url('/user?id=' . rawurlencode($redirectUser)));
        }
        $this->redirect(AdminAuth::url('/promotions'));
    }

    private function findPromotion(int $id): ?array
    {
        self::ensurePromotionSchema();
        if ($id <= 0) {
            return null;
        }

        $stmt = AdminDatabase::pdo()->prepare('SELECT * FROM promotions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function ensurePost(): void
    {
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }
    }

    private function flash(string $message): void
    {
        $_SESSION['admin_flash'] = $message;
    }

    private function isModalRequest(): bool
    {
        return (string) ($_GET['modal'] ?? '') === '1'
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }

    private static function ensurePromotionSchema(): void
    {
        if (self::$promotionSchemaEnsured) {
            return;
        }

        self::$promotionSchemaEnsured = true;

        // Şema kolonlarını (link_url, category, genişletilmiş image_url) otomatik
        // oluşturur/onarır ve admin/upload/bonuses görsellerini /uploads/promotions/
        // altına senkronize ederek kayıp görselleri kendiliğinden düzeltir.
        // Hem local hem canlıda her istek akışında güvenle çalışır.
        PromotionMediaGuard::bootstrap();
    }
}
