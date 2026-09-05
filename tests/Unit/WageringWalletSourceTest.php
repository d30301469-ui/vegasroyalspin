<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WageringWalletSourceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-07-29 12:00:00');
        $this->pdo->sqliteCreateFunction('GREATEST', static fn (float $a, float $b): float => max($a, $b));
        if (!class_exists('WageringService', false)) {
            require_once dirname(__DIR__, 2) . '/shared/services/WageringService.php';
        }
        $this->pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                balance REAL NOT NULL DEFAULT 0,
                bonus_balance REAL NOT NULL DEFAULT 0,
                wagering_required REAL NOT NULL DEFAULT 0,
                wagering_progress REAL NOT NULL DEFAULT 0,
                active_wallet_mode TEXT NOT NULL DEFAULT "main"
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE user_active_bonuses (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                wagering_target REAL NOT NULL,
                total_bet_amount REAL NOT NULL DEFAULT 0,
                current_bonus_balance REAL NOT NULL DEFAULT 0,
                is_complete INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT "active",
                completed_at TEXT NULL
            )'
        );
        $this->pdo->exec(
            "INSERT INTO users
                (id, balance, bonus_balance, wagering_required, wagering_progress, active_wallet_mode)
             VALUES (1, 100, 50, 100, 0, 'main')"
        );
        $this->pdo->exec(
            "INSERT INTO user_active_bonuses
                (id, user_id, wagering_target, total_bet_amount, current_bonus_balance, status)
             VALUES (1, 1, 100, 0, 50, 'active')"
        );
    }

    public function testExplicitBonusWalletTracksBonusDespiteCurrentGlobalMode(): void
    {
        WageringService::registerBet($this->pdo, 1, 25, 'bonus_balance');

        $this->assertSame(25.0, $this->value('wagering_progress', 'users'));
        $this->assertSame(25.0, $this->value('total_bet_amount', 'user_active_bonuses'));
    }

    public function testMainWalletDoesNotAdvanceBonusWagering(): void
    {
        WageringService::registerBet($this->pdo, 1, 25, 'balance');

        $this->assertSame(25.0, $this->value('wagering_progress', 'users'));
        $this->assertSame(0.0, $this->value('total_bet_amount', 'user_active_bonuses'));
        $this->assertSame('active', $this->pdo->query("SELECT status FROM user_active_bonuses LIMIT 1")->fetchColumn());
    }

    public function testBonusExpiresWhenCashBalanceRunsOutBeforeWagering(): void
    {
        $this->pdo->exec('UPDATE users SET balance = 0, bonus_balance = 40 WHERE id = 1');
        WageringService::registerBet($this->pdo, 1, 10, 'balance');

        $this->assertSame('expired', $this->pdo->query("SELECT status FROM user_active_bonuses LIMIT 1")->fetchColumn());
        $this->assertSame(0.0, $this->value('current_bonus_balance', 'user_active_bonuses'));
        $this->assertSame(0.0, $this->value('bonus_balance', 'users'));
    }

    public function testBonusExpiresWhenBonusBalanceHitsZero(): void
    {
        $this->pdo->exec('UPDATE users SET balance = 80, bonus_balance = 0 WHERE id = 1');
        WageringService::registerBet($this->pdo, 1, 10, 'bonus_balance');

        $this->assertSame('expired', $this->pdo->query("SELECT status FROM user_active_bonuses LIMIT 1")->fetchColumn());
    }

    public function testRefundReversesTheOriginalBonusWalletProgress(): void
    {
        WageringService::registerBet($this->pdo, 1, 30, 'bonus_balance');
        WageringService::setActiveWalletMode($this->pdo, 1, 'main');
        WageringService::reverseBet($this->pdo, 1, 10, 'bonus_balance');

        $this->assertSame(20.0, $this->value('wagering_progress', 'users'));
        $this->assertSame(20.0, $this->value('total_bet_amount', 'user_active_bonuses'));
    }

    private function value(string $column, string $table): float
    {
        return (float) $this->pdo->query("SELECT {$column} FROM {$table} LIMIT 1")->fetchColumn();
    }
}
