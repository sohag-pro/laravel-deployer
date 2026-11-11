<?php

namespace Tests\Unit;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DependencyVersionsTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function minimumVersions(): array
    {
        return [
            'laravel/framework' => ['laravel/framework', '13.30.0'],
            'league/commonmark' => ['league/commonmark', '2.10.2'],
            'guzzlehttp/guzzle' => ['guzzlehttp/guzzle', '7.15.2'],
            'guzzlehttp/psr7' => ['guzzlehttp/psr7', '2.12.3'],
            'symfony/http-foundation' => ['symfony/http-foundation', '7.4.19'],
            'symfony/yaml' => ['symfony/yaml', '7.4.18'],
            'league/flysystem' => ['league/flysystem', '3.35.3'],
        ];
    }

    #[DataProvider('minimumVersions')]
    public function test_package_is_at_or_above_its_security_fixed_version(string $package, string $minimum): void
    {
        $this->assertTrue(
            InstalledVersions::satisfies(new VersionParser, $package, '>='.$minimum),
            sprintf('%s must be installed at >= %s.', $package, $minimum)
        );
    }
}
