<?php

declare(strict_types=1);

final class TelegramMiniAppController extends Controller
{
    public function index(): void
    {
        require BASE_PATH . '/pages/tg.php';
    }
}
