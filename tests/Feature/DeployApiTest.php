<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DeployLauncher;
use App\Support\DeployState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeployApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/deployer_api_'.uniqid();
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

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/api/deploy')->assertUnauthorized();
        $this->getJson('/api/deploy/status')->assertUnauthorized();
    }

    public function test_token_user_can_trigger_a_deploy(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $launcher = $this->fakeLauncher();

        $this->postJson('/api/deploy', ['ref' => 'main'])
            ->assertStatus(202)
            ->assertJsonPath('message', 'Deploy started.');

        $this->assertTrue($launcher->launched);
        $this->assertSame('main', $launcher->ref);
        $this->assertSame(DeployState::RUNNING, DeployState::status()['status']);
    }

    public function test_trigger_is_conflicted_while_running(): void
    {
        DeployState::markStarted('busy');
        Sanctum::actingAs(User::factory()->create());
        $launcher = $this->fakeLauncher();

        $this->postJson('/api/deploy')->assertStatus(409);
        $this->assertFalse($launcher->launched);
    }

    public function test_invalid_ref_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $launcher = $this->fakeLauncher();

        $this->postJson('/api/deploy', ['ref' => 'bad;rm'])->assertStatus(422);
        $this->assertFalse($launcher->launched);
    }

    public function test_status_endpoint_returns_state(): void
    {
        DeployState::markStarted('run-1');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/deploy/status')
            ->assertOk()
            ->assertJson(['status' => DeployState::RUNNING, 'running' => true]);
    }

    public function test_token_command_creates_a_token(): void
    {
        $user = User::factory()->create();

        $this->artisan('deployer:token', ['name' => 'ci', '--email' => $user->email])
            ->assertExitCode(0);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'ci',
        ]);
    }
}
