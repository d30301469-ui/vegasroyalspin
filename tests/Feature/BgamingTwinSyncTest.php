<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * The BGaming provider ships as two physical copies that back two different
 * deployment topologies:
 *   - Monorepo git deploy  -> loads the root  services/ + controllers/ copies.
 *   - Standalone admin bundle -> loads the admin/services/ + admin/controllers/ copies.
 *
 * Because a wallet bugfix applied to only one copy would silently ship broken
 * behaviour to one topology, this test enforces byte-for-byte parity (line
 * endings normalised) so drift can never be merged or deployed unnoticed.
 */
final class BgamingTwinSyncTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function twinFileProvider(): array
    {
        return [
            'BgamingService' => [
                'services/BgamingService.php',
                'admin/services/BgamingService.php',
            ],
            'ApiBgamingWalletController' => [
                'controllers/Api/ApiBgamingWalletController.php',
                'admin/controllers/Api/ApiBgamingWalletController.php',
            ],
        ];
    }

    /**
     * @dataProvider twinFileProvider
     */
    public function testTwinCopiesStayIdentical(string $rootRelative, string $adminRelative): void
    {
        $root = $this->readNormalized($rootRelative);
        $admin = $this->readNormalized($adminRelative);

        $this->assertSame(
            $admin,
            $root,
            sprintf(
                'BGaming twin files have drifted: %s and %s must stay identical. '
                . 'Apply every change to BOTH copies.',
                $rootRelative,
                $adminRelative
            )
        );
    }

    private function readNormalized(string $relativePath): string
    {
        $path = rtrim((string) BASE_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

        $contents = file_get_contents($path);
        $this->assertIsString($contents, 'Unable to read ' . $relativePath);

        // Normalise line endings so Git autocrlf / editor settings never cause
        // false positives; we only care about the actual code content.
        return str_replace(["\r\n", "\r"], "\n", (string) $contents);
    }
}
