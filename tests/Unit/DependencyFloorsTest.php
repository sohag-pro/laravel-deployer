<?php

namespace Tests\Unit;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DependencyFloorsTest extends TestCase
{
    #[DataProvider('fixedVersions')]
    public function test_package_is_installed_at_or_above_fixed_version(string $package, string $floor): void
    {
        $this->assertTrue(
            InstalledVersions::isInstalled($package),
            sprintf('%s is not installed (required floor %s).', $package, $floor)
        );

        $installed = InstalledVersions::getVersion($package);

        $this->assertNotNull(
            $installed,
            sprintf('%s has no resolvable installed version (required floor %s).', $package, $floor)
        );

        $normalised = ltrim($installed, 'v');

        $this->assertTrue(
            version_compare($normalised, $floor, '>='),
            sprintf('%s is installed at %s, below the required floor %s.', $package, $installed, $floor)
        );
    }

    public static function fixedVersions(): array
    {
        return [
            'laravel/framework' => ['laravel/framework', '13.40.0'],
            'guzzlehttp/guzzle' => ['guzzlehttp/guzzle', '7.15.2'],
            'guzzlehttp/psr7' => ['guzzlehttp/psr7', '2.12.3'],
            'league/commonmark' => ['league/commonmark', '2.10.2'],
            'league/flysystem' => ['league/flysystem', '3.35.3'],
            'symfony/http-foundation' => ['symfony/http-foundation', '7.4.19'],
            'symfony/yaml' => ['symfony/yaml', '7.4.18'],
        ];
    }
}
