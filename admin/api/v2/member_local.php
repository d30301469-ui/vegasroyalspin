<?php
/**
 * Frontend local dispatch entry — admin/api/v2 route modüllerini doğrudan kullanır.
 * PublicMemberApiDispatcher tarafından require edilir; $_GET['route'] önceden set edilmelidir.
 */

require_once __DIR__ . '/bootstrap-member-local.php';
require __DIR__ . '/includes/member_api_kernel.php';
require __DIR__ . '/includes/member_route_loader.php';

$error(404, 'API endpoint bulunamadı.', ['method' => $method, 'route' => $route]);
