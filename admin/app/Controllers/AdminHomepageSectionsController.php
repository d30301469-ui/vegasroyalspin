<?php

declare(strict_types=1);

final class AdminHomepageSectionsController extends AdminController
{
    private const ADMIN_SURFACE = 'all';

    public function edit(): void
    {
        $this->requirePermission('homepage-sections');
        $this->loadHomepageApi();
        ApiHomepageSections::ensureStorage();

        $this->view('homepage-sections/edit', [
            'title' => 'Ana Sayfa Vitrin Yönetimi',
            'active' => 'datatable',
            'moduleKey' => 'homepage-sections',
            'crumbs' => 'Content | Homepage Sections',
            'sections' => $this->sectionsByKey(),
            'flash' => $this->pullFlash(),
            'error' => $this->pullError(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('homepage-sections');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            return;
        }

        $this->loadHomepageApi();
        ApiHomepageSections::ensureStorage();

        $postedSections = is_array($_POST['sections'] ?? null) ? $_POST['sections'] : [];
        $postedCards = is_array($_POST['cards'] ?? null) ? $_POST['cards'] : [];
        $pdo = AdminDatabase::pdo();

        foreach ($this->editableSectionKeys() as $sectionKey => $type) {
            $sectionInput = is_array($postedSections[$sectionKey] ?? null) ? $postedSections[$sectionKey] : [];
            $title = trim((string) ($sectionInput['title'] ?? ''));
            $surface = self::ADMIN_SURFACE;
            $sortOrder = (int) ($sectionInput['sort_order'] ?? 0);
            $isActive = (int) ($sectionInput['is_active'] ?? 0) === 1 ? 1 : 0;
            $startDate = $this->nullableDate((string) ($sectionInput['start_date'] ?? ''));
            $endDate = $this->nullableDate((string) ($sectionInput['end_date'] ?? ''));

            $payload = $type === 'banner'
                ? $this->bannerPayload($sectionInput)
                : $this->gamesPayload(
                    $sectionKey,
                    $sectionInput,
                    is_array($postedCards[$sectionKey] ?? null) ? $postedCards[$sectionKey] : []
                );

            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                $_SESSION['admin_homepage_sections_error'] = 'Vitrin verisi kaydedilemedi.';
                $this->redirect(AdminAuth::url('/homepage-sections'));
            }

            $lookup = $pdo->prepare(
                'SELECT id FROM homepage_sections
                 WHERE section_key = :section_key AND surface = :surface
                 LIMIT 1'
            );
            $lookup->execute([
                'section_key' => $sectionKey,
                'surface' => $surface,
            ]);
            $existingId = (int) $lookup->fetchColumn();

            if ($existingId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE homepage_sections
                     SET title = :title,
                         type = :type,
                         payload = :payload,
                         sort_order = :sort_order,
                         is_active = :is_active,
                         start_date = :start_date,
                         end_date = :end_date
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => $existingId,
                    'title' => $title,
                    'type' => $type,
                    'payload' => $encoded,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO homepage_sections
                        (section_key, title, type, surface, payload, sort_order, is_active, start_date, end_date)
                     VALUES
                        (:section_key, :title, :type, :surface, :payload, :sort_order, :is_active, :start_date, :end_date)'
                );
                $stmt->execute([
                    'section_key' => $sectionKey,
                    'title' => $title,
                    'type' => $type,
                    'surface' => $surface,
                    'payload' => $encoded,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);
            }
        }

        $_SESSION['admin_homepage_sections_flash'] = 'Ana sayfa vitrinleri güncellendi.';
        if (function_exists('metropol_notify_frontend_cms_purge')) {
            metropol_notify_frontend_cms_purge('homepage_sections');
        }
        // HTTP purge başarısız olsa bile aynı host/monorepo kurulumda anında yenileme.
        if (class_exists('ApiCmsRemote', false)) {
            ApiCmsRemote::purgeCache('homepage_sections');
        }
        $this->redirect(AdminAuth::url('/homepage-sections'));
    }

    private function sectionsByKey(): array
    {
        $sections = [];
        foreach (ApiHomepageSections::defaultSections('all', '', true) as $section) {
            $sections[$section['section_key']] = $section;
        }

        $keys = array_keys($this->editableSectionKeys());
        $sectionPlaceholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = AdminDatabase::pdo()->prepare(
            'SELECT id, section_key, title, type, surface, payload, sort_order, is_active, start_date, end_date, updated_at
             FROM homepage_sections
             WHERE section_key IN (' . $sectionPlaceholders . ')
               AND surface = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([...$keys, self::ADMIN_SURFACE]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $section = ApiHomepageSections::sectionForAdminEdit($row, false);
            if ($section['section_key'] !== '') {
                $sections[$section['section_key']] = $section;
            }
        }

        return $sections;
    }

    private function loadHomepageApi(): void
    {
        if (!defined('API_PATH')) {
            define('API_PATH', admin_project_path('api'));
        }
        require_once API_PATH . '/bootstrap.php';
    }

    private function editableSectionKeys(): array
    {
        return [
            'withdrawal-banner' => 'banner',
            'casino' => 'games',
            'live-casino' => 'games',
        ];
    }

    private function bannerPayload(array $input): array
    {
        return [
            'image_url' => $this->storageMediaPath((string) ($input['image_url'] ?? '')),
            'alt' => trim((string) ($input['alt'] ?? '')),
            'href' => trim((string) ($input['href'] ?? '')),
            'onclick' => trim((string) ($input['onclick'] ?? '')),
        ];
    }

    private function gamesPayload(string $sectionKey, array $sectionInput, array $cards): array
    {
        $items = [];
        $titles = is_array($cards['title'] ?? null) ? $cards['title'] : [];
        $gameIds = is_array($cards['game_id'] ?? null) ? $cards['game_id'] : [];
        $pdo = null;
        try {
            $pdo = AdminDatabase::pdo();
        } catch (Throwable) {
            $pdo = null;
        }

        foreach ($titles as $index => $titleValue) {
            $title = trim((string) $titleValue);
            $image = $this->storageMediaPath((string) ($cards['image_url'][$index] ?? ''));
            if ($title === '' || $image === '') {
                continue;
            }
            $size = (string) ($cards['size'][$index] ?? 'normal');
            $imageFit = (string) ($cards['image_fit'][$index] ?? 'fill');
            $imageFit = in_array($imageFit, ['cover', 'fill'], true) ? $imageFit : 'fill';
            $imageScale = (int) ($cards['image_scale'][$index] ?? 100);
            $imageScale = max(40, min(120, $imageScale));

            $rawGameId = $gameIds[$index] ?? '';
            // Never cast to int — alphanumeric codes like vs20fruitswx become 0.
            $gameId = class_exists('ApiHomepageSections', false)
                ? ApiHomepageSections::normalizeGameIdValue($rawGameId)
                : trim((string) $rawGameId);
            if ($gameId === '0') {
                $gameId = '';
            }
            if ($pdo instanceof PDO && class_exists('ApiHomepageSections', false)) {
                $hydrated = ApiHomepageSections::hydrateGamesPayloadWithCatalog([
                    'items' => [[
                        'game_id' => $gameId,
                        'title' => $title,
                    ]],
                ], $sectionKey);
                $resolved = trim((string) ($hydrated['items'][0]['game_id'] ?? ''));
                if ($resolved !== '' && $resolved !== '0') {
                    $gameId = $resolved;
                }
            }

            $items[] = [
                'game_id' => $gameId,
                'title' => $title,
                'image_url' => $image,
                'alt' => trim((string) ($cards['alt'][$index] ?? $title)),
                'size' => $size === 'featured' ? 'featured' : 'normal',
                'image_fit' => $imageFit,
                'image_scale' => $imageScale,
                'link' => trim((string) ($cards['link'][$index] ?? '')),
                'sort_order' => (int) ($cards['sort_order'][$index] ?? (($index + 1) * 10)),
                'is_active' => (int) ($cards['is_active'][$index] ?? 0) === 1,
            ];
        }

        usort($items, static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order']));

        return [
            'href' => trim((string) ($sectionInput['href'] ?? '')),
            'items' => $items,
        ];
    }

    private function storageMediaPath(string $path): string
    {
        if (class_exists('ApiMediaUrl', false)) {
            return ApiMediaUrl::storagePath($path);
        }

        return ltrim(trim($path), '/');
    }

    private function nullableDate(string $value): ?string
    {
        $value = trim($value);
        return $value !== '' ? str_replace('T', ' ', $value) : null;
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_homepage_sections_flash'] ?? '');
        unset($_SESSION['admin_homepage_sections_flash']);
        return $message;
    }

    private function pullError(): string
    {
        $message = (string) ($_SESSION['admin_homepage_sections_error'] ?? '');
        unset($_SESSION['admin_homepage_sections_error']);
        return $message;
    }
}
