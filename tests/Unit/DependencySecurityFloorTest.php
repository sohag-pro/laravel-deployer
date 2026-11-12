<?php

namespace Tests\Unit;

use Composer\InstalledVersions;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DependencySecurityFloorTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: string, 2: bool}>
     */
    public static function floorComparisons(): array
    {
        return [
            'one patch below floor fails' => ['13.29.9.0', '13.30.0', false],
            'one patch below floor fails (commonmark)' => ['2.10.1.0', '2.10.2', false],
            'null installed version fails' => [null, '13.30.0', false],
            'null fails against any floor' => [null, '0.0.1', false],
            'normalized four-part version equal to floor passes' => ['13.30.0.0', '13.30.0', true],
            'higher minor passes' => ['13.31.0.0', '13.30.0', true],
            'higher major passes' => ['14.0.0.0', '13.30.0', true],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function packageFloors(): array
    {
        return [
            'laravel/framework' => ['laravel/framework', '13.30.0'],
            'guzzlehttp/guzzle' => ['guzzlehttp/guzzle', '7.15.2'],
            'guzzlehttp/psr7' => ['guzzlehttp/psr7', '2.12.3'],
            'league/commonmark' => ['league/commonmark', '2.10.2'],
            'league/flysystem' => ['league/flysystem', '3.35.3'],
            'symfony/http-foundation' => ['symfony/http-foundation', '7.4.19'],
            'symfony/yaml' => ['symfony/yaml', '7.4.18'],
        ];
    }

    #[DataProvider('floorComparisons')]
    public function test_compares_installed_version_with_floor(?string $installed, string $floor, bool $expected): void
    {
        $this->assertSame($expected, self::meetsFloor($installed, $floor));
    }

    #[DataProvider('packageFloors')]
    public function test_package_is_installed_at_or_above_security_floor(string $package, string $floor): void
    {
        try {
            $installed = InstalledVersions::getVersion($package);
        } catch (OutOfBoundsException $e) {
            $this->fail("Package {$package} is not installed (required floor {$floor}).");
        }

        if ($installed === null) {
            $this->fail("Package {$package} has no installed version (required floor {$floor}).");
        }

        $this->assertTrue(
            self::meetsFloor($installed, $floor),
            "Package {$package} is installed at {$installed}, below the required floor {$floor}."
        );
    }

    private static function meetsFloor(?string $installed, string $floor): bool
    {
        if ($installed === null) {
            return false;
        }

        return version_compare($installed, $floor, '>=');
    }
}
