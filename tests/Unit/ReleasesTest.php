<?php

namespace Tests\Unit;

use App\Support\Releases;
use PHPUnit\Framework\TestCase;

class ReleasesTest extends TestCase
{
    protected string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/deployer_rel_'.uniqid();
        mkdir($this->base, 0755, true);
    }

    protected function tearDown(): void
    {
        @exec('rm -rf '.escapeshellarg($this->base));
        parent::tearDown();
    }

    public function test_switch_points_serve_at_release_and_current_reports_it(): void
    {
        $release = $this->base.'/releases/2024-01-01';
        $serve = $this->base.'/current';
        mkdir($release, 0755, true);

        Releases::switch($serve, $release);

        $this->assertTrue(is_link($serve));
        $this->assertSame($release, readlink($serve));
        $this->assertSame('2024-01-01', Releases::current($serve));
    }

    public function test_switch_replaces_previous_symlink_atomically(): void
    {
        $a = $this->base.'/releases/a';
        $b = $this->base.'/releases/b';
        $serve = $this->base.'/current';
        mkdir($a, 0755, true);
        mkdir($b, 0755, true);

        Releases::switch($serve, $a);
        $this->assertSame('a', Releases::current($serve));

        Releases::switch($serve, $b);
        $this->assertSame('b', Releases::current($serve));
        $this->assertSame($b, readlink($serve));
    }

    public function test_switch_migrates_a_legacy_real_directory(): void
    {
        $release = $this->base.'/releases/v1';
        $serve = $this->base.'/current';
        mkdir($release, 0755, true);

        // Legacy serve path: a real directory with stray contents.
        mkdir($serve, 0755, true);
        file_put_contents($serve.'/old.txt', 'x');

        Releases::switch($serve, $release);

        $this->assertTrue(is_link($serve));
        $this->assertSame($release, readlink($serve));
    }

    public function test_current_returns_null_when_serve_is_not_a_symlink(): void
    {
        $this->assertNull(Releases::current($this->base.'/missing'));
    }
}
