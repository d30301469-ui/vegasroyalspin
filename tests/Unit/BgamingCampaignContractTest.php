<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BgamingCampaignContractTest extends TestCase
{
    /** Dakikaya hizalı sabit: datetime-local değerleri saniye taşımaz. */
    private const NOW = 1780000200;

    /**
     * @return array<string, mixed>
     */
    private function validFreespinInput(array $overrides = []): array
    {
        return array_replace([
            'campaign_type' => 'freespin',
            'title' => 'Hoş geldin freespin',
            'game_identifier' => 'CarnivalBonanza',
            'freespins_per_player' => '25',
            'currency_code' => 'TRY',
            'bet_level' => '2',
            'expires_at' => date('Y-m-d\TH:i', self::NOW + 86400),
            'active' => '1',
        ], $overrides);
    }

    public function testValidFreespinInputIsNormalized(): void
    {
        $result = BgamingService::validateCampaignInput($this->validFreespinInput(), self::NOW);

        $this->assertSame([], $result['errors']);
        $this->assertSame('freespin', $result['values']['campaign_type']);
        $this->assertSame(25, $result['values']['freespins_per_player']);
        $this->assertSame(2, $result['values']['bet_level']);
        $this->assertSame('TRY', $result['values']['currency_code']);
        $this->assertSame(1, $result['values']['active']);
        $this->assertSame(self::NOW + 86400, $result['values']['expires_at']);
    }

    public function testFreespinCampaignRequiresGameSpinsAndExpiry(): void
    {
        $result = BgamingService::validateCampaignInput([
            'campaign_type' => 'freespin',
            'title' => '',
            'game_identifier' => '',
            'freespins_per_player' => '0',
            'active' => '1',
        ], self::NOW);

        $this->assertArrayHasKey('title', $result['errors']);
        $this->assertArrayHasKey('game_identifier', $result['errors']);
        $this->assertArrayHasKey('freespins_per_player', $result['errors']);
        $this->assertArrayHasKey('expires_at', $result['errors']);
    }

    public function testActiveFreespinCampaignRejectsPastExpiry(): void
    {
        $result = BgamingService::validateCampaignInput(
            $this->validFreespinInput(['expires_at' => date('Y-m-d\TH:i', self::NOW - 3600)]),
            self::NOW
        );

        $this->assertArrayHasKey('expires_at', $result['errors']);
    }

    public function testInactiveCampaignMayKeepPastExpiry(): void
    {
        $result = BgamingService::validateCampaignInput(
            $this->validFreespinInput([
                'expires_at' => date('Y-m-d\TH:i', self::NOW - 3600),
                'active' => '0',
            ]),
            self::NOW
        );

        $this->assertSame([], $result['errors']);
    }

    public function testBeginsAtMustPrecedeExpiresAt(): void
    {
        $result = BgamingService::validateCampaignInput(
            $this->validFreespinInput([
                'begins_at' => date('Y-m-d\TH:i', self::NOW + 172800),
                'expires_at' => date('Y-m-d\TH:i', self::NOW + 86400),
            ]),
            self::NOW
        );

        $this->assertArrayHasKey('begins_at', $result['errors']);
    }

    public function testPromoCampaignRequiresAmountButNotGameOrExpiry(): void
    {
        $missingAmount = BgamingService::validateCampaignInput([
            'campaign_type' => 'promo',
            'title' => 'Promo',
            'promo_amount' => '0',
            'active' => '1',
        ], self::NOW);
        $this->assertSame(['promo_amount' => 'Promo tutarı 0 dan büyük olmalıdır.'], $missingAmount['errors']);

        $valid = BgamingService::validateCampaignInput([
            'campaign_type' => 'promo',
            'title' => 'Promo',
            'promo_amount' => '250.50',
            'wagering_multiplier' => '3',
            'active' => '1',
        ], self::NOW);
        $this->assertSame([], $valid['errors']);
        $this->assertSame(250.5, $valid['values']['promo_amount']);
        $this->assertSame(3.0, $valid['values']['wagering_multiplier']);
    }

    public function testCampaignCodeCharsetIsValidated(): void
    {
        $invalid = BgamingService::validateCampaignInput(
            $this->validFreespinInput(['campaign_code' => 'kod ile boşluk']),
            self::NOW
        );
        $this->assertArrayHasKey('campaign_code', $invalid['errors']);

        $valid = BgamingService::validateCampaignInput(
            $this->validFreespinInput(['campaign_code' => 'bg_freespin_welcome-1']),
            self::NOW
        );
        $this->assertSame([], $valid['errors']);
    }

    public function testUnknownCampaignTypeFallsBackToFreespin(): void
    {
        $result = BgamingService::validateCampaignInput(
            $this->validFreespinInput(['campaign_type' => 'whatever']),
            self::NOW
        );

        $this->assertSame('freespin', $result['values']['campaign_type']);
    }

    public function testGameIdentifierPrefixIsStripped(): void
    {
        $result = BgamingService::validateCampaignInput(
            $this->validFreespinInput(['game_identifier' => 'bgaming:CarnivalBonanza']),
            self::NOW
        );

        $this->assertSame('CarnivalBonanza', $result['values']['game_identifier']);
    }

    public function testIssueIdIsStablePerCampaignAndUser(): void
    {
        $first = BgamingService::freespinIssueId('bg_freespin_abc', 42);

        $this->assertSame($first, BgamingService::freespinIssueId('bg_freespin_abc', 42));
        $this->assertNotSame($first, BgamingService::freespinIssueId('bg_freespin_abc', 43));
        $this->assertNotSame($first, BgamingService::freespinIssueId('bg_freespin_xyz', 42));
        $this->assertStringStartsWith('fs_assign_42_', $first);
    }

    public function testStatusLabelsAreTurkishAndFallBackToRawValue(): void
    {
        $this->assertSame('Aktif', BgamingService::freespinStatusLabel('active'));
        $this->assertSame('Oynandı', BgamingService::freespinStatusLabel('played'));
        $this->assertSame('Başarısız', BgamingService::freespinStatusLabel('failed'));
        $this->assertSame('Hazırlanıyor', BgamingService::freespinStatusLabel('PENDING'));
        $this->assertSame('Bilinmiyor', BgamingService::freespinStatusLabel(''));
        $this->assertSame('weird_state', BgamingService::freespinStatusLabel('weird_state'));
    }

    public function testCampaignExceptionCarriesFieldErrors(): void
    {
        $exception = new BgamingCampaignException([
            'expires_at' => 'Bitiş tarihi zorunludur.',
            'title' => 'Başlık zorunludur.',
        ]);

        $this->assertSame('Bitiş tarihi zorunludur.', $exception->getMessage());
        $this->assertArrayHasKey('title', $exception->errors());
    }
}
