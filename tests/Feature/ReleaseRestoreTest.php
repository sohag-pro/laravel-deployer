<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Releases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/deployer_restore_'.uniqid();
        mkdir($this->base.'/releases/2024-05-01', 0755, true);

        config([
            'deployer.base_dir' => $this->base,
            'deployer.version_dir' => '/releases',
            'deployer.serve_dir' => $this->base.'/current',
        ]);
    }

    protected function tearDown(): void
    {
        @exec('rm -rf '.escapeshellarg($this->base));
        parent::tearDown();
    }

    public function test_restore_switches_the_serve_symlink(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/restore/2024-05-01')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('2024-05-01', Releases::current($this->base.'/current'));
    }

    public function test_restore_reports_a_missing_release(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/restore/2099-01-01')
            ->assertSessionHas('error');
    }
}
