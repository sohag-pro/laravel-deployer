<?php

namespace Tests\Unit;

use App\Support\Retention;
use PHPUnit\Framework\TestCase;

class RetentionTest extends TestCase
{
    protected string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/deployer_ret_'.uniqid();
        mkdir($this->base, 0755, true);
    }

    protected function tearDown(): void
    {
        @exec('rm -rf '.escapeshellarg($this->base));
        parent::tearDown();
    }

    public function test_prune_releases_keeps_newest_n_and_the_live_release(): void
    {
        $dir = $this->base.'/releases';
        mkdir($dir, 0755, true);
        foreach (['2024-01-01', '2024-01-02', '2024-01-03', '2024-01-04', '2024-01-05', '2024-01-06'] as $r) {
            mkdir($dir.'/'.$r, 0755, true);
            file_put_contents($dir.'/'.$r.'.zip', 'z');
        }

        // Keep 3 newest (04,05,06) plus the live release (01, the oldest).
        $removed = Retention::pruneReleases($dir, 3, '2024-01-01');

        sort($removed);
        $this->assertSame(['2024-01-02', '2024-01-03'], $removed);
        $this->assertDirectoryExists($dir.'/2024-01-01');
        $this->assertDirectoryExists($dir.'/2024-01-04');
        $this->assertDirectoryDoesNotExist($dir.'/2024-01-02');
        $this->assertFileDoesNotExist($dir.'/2024-01-02.zip');
    }

    public function test_prune_releases_is_a_noop_when_keep_is_zero(): void
    {
        $dir = $this->base.'/releases';
        mkdir($dir.'/a', 0755, true);
        mkdir($dir.'/b', 0755, true);

        $this->assertSame([], Retention::pruneReleases($dir, 0));
        $this->assertDirectoryExists($dir.'/a');
    }

    public function test_prune_dumps_keeps_newest_n(): void
    {
        $dir = $this->base.'/db';
        mkdir($dir, 0755, true);
        foreach (['db-1.sql', 'db-2.sql', 'db-3.sql', 'db-4.sql'] as $f) {
            file_put_contents($dir.'/'.$f, 'x');
        }

        $removed = Retention::pruneDumps($dir, 2);

        sort($removed);
        $this->assertSame(['db-1.sql', 'db-2.sql'], $removed);
        $this->assertFileExists($dir.'/db-4.sql');
        $this->assertFileDoesNotExist($dir.'/db-1.sql');
    }
}
