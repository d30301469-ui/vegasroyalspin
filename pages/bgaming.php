<?php
/**
 * Legacy entry — /bgaming is routed via BgamingController.
 * Kept so direct includes still resolve to the dedicated controller.
 */
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    frontend_session_start();
}
require_once __DIR__ . '/../core/bootstrap.php';
require_once CONTROLLER_PATH . '/BgamingController.php';

(new BgamingController())->index();
