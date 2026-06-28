<?php

/**
 * Duyurular — doğrudan bu dosyaya gelen istekler (rewrite olmayan /api/ yapılandırmaları).
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/controllers/Api/ApiAnnouncementsController.php';

(new ApiAnnouncementsController())->index();
