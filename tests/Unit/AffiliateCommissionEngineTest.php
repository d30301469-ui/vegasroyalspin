<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AffiliateCommissionEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $engine = dirname(__DIR__, 2) . '/shared/services/AffiliateCommissionEngine.php';
        if (is_file($engine)) {
            require_once $engine;
        }
        $service = dirname(__DIR__, 2) . '/shared/services/AffiliateService.php';
        if (is_file($service)) {
            require_once $service;
        }
    }

    public function testPaidStatusSqlIncludesCompletedStates(): void
    {
        $sql = AffiliateCommissionEngine::paidStatusSql();
        self::assertStringContainsString('confirmed', $sql);
        self::assertStringContainsString('completed', $sql);
    }
}
