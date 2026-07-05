<?php

declare(strict_types=1);

/**
 * Split frontend: lightweight /api/* handler (no full page bootstrap / session).
 */
function metropol_request_is_fast_public_api(string $path): bool
{
    $path = '/' . trim($path, '/');

    if (str_starts_with($path, '/api/')) {
        return true;
    }

    return false;
}

function metropol_public_api_route_from_path(string $path): string
{
    $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?: '', '/');

    if (str_starts_with($path, '/api/v2/')) {
        return trim(substr($path, strlen('/api/v2/')), '/');
    }
    if (str_starts_with($path, '/api/member/')) {
        return trim(substr($path, strlen('/api/member/')), '/');
    }
    if (str_starts_with($path, '/api/content/')) {
        return 'content/' . trim(substr($path, strlen('/api/content/')), '/');
    }

    $legacy = [
        '/api/sliders' => 'content/sliders',
        '/api/sliders.php' => 'content/sliders',
        '/api/auth-sliders' => 'content/auth-sliders',
        '/api/auth_sliders.php' => 'content/auth-sliders',
        '/api/site_settings' => 'site_settings.php',
        '/api/site_settings.php' => 'site_settings.php',
        '/api/site-settings' => 'site_settings.php',
        '/api/site-settings.php' => 'site_settings.php',
        '/api/winners' => 'winners.php',
        '/api/winners.php' => 'winners.php',
        '/api/announcements' => 'announcements.php',
        '/api/announcements.php' => 'announcements.php',
        '/api/track_visit' => 'track_visit.php',
        '/api/track_visit.php' => 'track_visit.php',
        '/api/track-visit' => 'track_visit.php',
        '/api/games' => 'games',
        '/api/games.php' => 'games.php',
        '/api/games-provider' => 'games_provider.php',
        '/api/games_provider.php' => 'games_provider.php',
        '/api/footer' => 'content/footer',
        '/api/footer.php' => 'content/footer',
        '/api/mobile-menu' => 'content/mobile-menu',
        '/api/mobile_menu.php' => 'content/mobile-menu',
        '/api/mobile-menu.php' => 'content/mobile-menu',
        '/api/homepage-sections' => 'content/homepage-sections',
        '/api/homepage_sections.php' => 'content/homepage-sections',
        '/api/homepage-sections.php' => 'content/homepage-sections',
        '/api/promotions' => 'content/promotions',
        '/api/promotions.php' => 'content/promotions',
        '/api/footer-pages' => 'content/footer-pages',
        '/api/footer_pages.php' => 'content/footer-pages',
    ];

    if (isset($legacy[$path])) {
        return $legacy[$path];
    }

    if (str_starts_with($path, '/api/') && !str_starts_with($path, '/api/v2/') && !str_starts_with($path, '/api/member/') && !str_starts_with($path, '/api/content/')) {
        return trim(substr($path, strlen('/api/')), '/');
    }

    return '';
}

function metropol_handle_public_api_request(?string $requestUri = null): void
{
    $requestUri ??= (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = '/' . trim((string) (parse_url($requestUri, PHP_URL_PATH) ?: ''), '/');
    if (!metropol_request_is_fast_public_api($path)) {
        // Bu fonksiyon /api/ prefix koşulundan sonra çağrılır; path uyuşmazlığı
        // normalizelemeden kaynaklanıyor olabilir — güvenli taraf olarak 404 dön.
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode(['success' => false, 'ok' => false, 'code' => 404, 'message' => 'Not found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    metropol_dispatch_frontend_public_api($requestUri);
    exit;
}

function metropol_dispatch_frontend_public_api(string $requestUri): void
{
    require_once __DIR__ . '/../config/paths.php';
    require_once BASE_PATH . '/config/bootstrap_api.php';
    require_once BASE_PATH . '/config/frontend_session.php';
    require_once SERVICE_PATH . '/PublicApiV2Dispatcher.php';

    $route = metropol_public_api_route_from_path($requestUri);
    if ($route === '') {
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'success' => false,
            'ok' => false,
            'code' => 404,
            'message' => 'Public API route not found.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    PublicApiV2Dispatcher::dispatch($route);
}
