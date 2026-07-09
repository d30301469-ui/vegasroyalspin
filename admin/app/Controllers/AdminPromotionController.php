<?php

declare(strict_types=1);

final class AdminPromotionController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('promotions');
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
        $this->view('promotions/form', [
            'title' => 'Promosyon Ekle',
            'active' => 'promotions',
            'crumbs' => 'Marketing | Promotions | Ekle',
            'promotion' => [],
            'mode' => 'create',
            'flash' => $this->pullFlash(),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('promotions');
        $this->ensurePost();

        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $this->flash('Başlık zorunludur.');
            $this->redirect(AdminAuth::url('/promotion/create'));
        }

        try {
            $imageUrl = self::normalizePromotionImageUrl((string) ($_POST['image_url'] ?? ''));
            AdminDatabase::pdo()->prepare(
                'INSERT INTO promotions (title, description, type, category, status, sort_order, image_url, bonus_amount, wagering_multiplier, created_at, updated_at)
                 VALUES (:title, :description, :type, :category, :status, :sort_order, :image_url, :bonus_amount, :wagering_multiplier, NOW(), NOW())'
            )->execute([
                'title' => $title,
                'description' => trim((string) ($_POST['description'] ?? '')),
                'type' => trim((string) ($_POST['type'] ?? '')),
                'category' => trim((string) ($_POST['category'] ?? '')),
                'status' => trim((string) ($_POST['status'] ?? 'active')),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'image_url' => $imageUrl,
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
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $promotion = $this->findPromotion($id);
        if ($promotion === null) {
            $this->flash('Promosyon bulunamadı.');
            $this->redirect(AdminAuth::url('/promotions'));
        }

        $this->view('promotions/form', [
            'title' => 'Promosyon Düzenle',
            'active' => 'promotions',
            'crumbs' => 'Marketing | Promotions | Düzenle',
            'promotion' => $promotion,
            'mode' => 'edit',
            'flash' => $this->pullFlash(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('promotions');
        $this->ensurePost();

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
            $imageUrl = self::normalizePromotionImageUrl((string) ($_POST['image_url'] ?? ''));
            AdminDatabase::pdo()->prepare(
                'UPDATE promotions SET title = :title, description = :description, type = :type, category = :category,
                 status = :status, sort_order = :sort_order, image_url = :image_url,
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

    public function delete(): void
    {
        $this->requirePermission('promotions');
        $this->ensurePost();

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

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }
}
