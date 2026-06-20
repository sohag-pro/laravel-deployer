<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DeployLauncher;
use App\Support\DeployState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsyncDeployTest extends TestCase
{
    use RefreshDatabase;

    protected string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/deployer_async_'.uniqid();
        mkdir($this->base, 0755, true);
        config(['deployer.base_dir' => $this->base, 'deployer.deploy_timeout' => 1800]);
    }

    protected function tearDown(): void
    {
        @exec('rm -rf '.escapeshellarg($this->base));
        parent::tearDown();
    }

    protected function fakeLauncher(): object
    {
        $launcher = new class extends DeployLauncher
        {
            public bool $launched = false;

            public ?string $ref = null;

            public function launch(?string $ref = null): void
            {
                $this->launched = true;
                $this->ref = $ref;
            }
        };

        $this->app->instance(DeployLauncher::class, $launcher);

        return $launcher;
    }

    public function test_deploy_marks_running_and_launches_in_background(): void
    {
        $launcher = $this->fakeLauncher();

        $this->actingAs(User::factory()->create())
            ->post('/deploy')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($launcher->launched);
        $this->assertSame(DeployState::RUNNING, DeployState::status()['status']);
    }

    public function test_deploy_passes_a_valid_ref_to_the_launcher(): void
    {
        $launcher = $this->fakeLauncher();

        $this->actingAs(User::factory()->create())
            ->post('/deploy', ['ref' => 'release/v1.2.3'])
            ->assertSessionHas('success');

        $this->assertTrue($launcher->launched);
        $this->assertSame('release/v1.2.3', $launcher->ref);
    }

    public function test_deploy_rejects_an_invalid_ref(): void
    {
        $launcher = $this->fakeLauncher();

        $this->actingAs(User::factory()->create())
            ->post('/deploy', ['ref' => 'foo;rm -rf /'])
            ->assertSessionHas('error');

        $this->assertFalse($launcher->launched);
    }

    public function test_deploy_is_refused_while_one_is_running(): void
    {
        DeployState::markStarted('in-progress');
        $launcher = $this->fakeLauncher();

        $this->actingAs(User::factory()->create())
            ->post('/deploy')
            ->assertSessionHas('error');

        $this->assertFalse($launcher->launched);
    }

    public function test_deploy_status_endpoint_returns_state_and_log(): void
    {
        DeployState::markStarted('run-1');

        $this->actingAs(User::factory()->create())
            ->getJson('/deploy-status')
            ->assertOk()
            ->assertJson(['status' => DeployState::RUNNING, 'running' => true]);
    }

    public function test_deploy_status_requires_authentication(): void
    {
        $this->get('/deploy-status')->assertRedirect(route('login'));
    }
}
