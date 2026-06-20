<?php

namespace Tests\Feature;

use App\Support\DeployLauncher;
use App\Support\DeployState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GitHubWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $base;

    protected string $secret = 'shhh-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/deployer_hook_'.uniqid();
        mkdir($this->base, 0755, true);
        config([
            'deployer.base_dir' => $this->base,
            'deployer.github_webhook_secret' => $this->secret,
            'deployer.webhook_branch' => null,
        ]);
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

    protected function send(array $payload, string $event = 'push', ?string $secret = null): TestResponse
    {
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret ?? $this->secret);

        return $this->call('POST', '/api/webhooks/github', [], [], [], [
            'HTTP_X_GITHUB_EVENT' => $event,
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_valid_push_triggers_a_deploy_of_the_pushed_branch(): void
    {
        $launcher = $this->fakeLauncher();

        $this->send(['ref' => 'refs/heads/main'])->assertStatus(202);

        $this->assertTrue($launcher->launched);
        $this->assertSame('main', $launcher->ref);
        $this->assertSame(DeployState::RUNNING, DeployState::status()['status']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $launcher = $this->fakeLauncher();

        $this->send(['ref' => 'refs/heads/main'], secret: 'wrong-secret')->assertStatus(401);

        $this->assertFalse($launcher->launched);
    }

    public function test_ping_event_is_acknowledged_without_deploying(): void
    {
        $launcher = $this->fakeLauncher();

        $this->send(['zen' => 'hi'], event: 'ping')
            ->assertOk()
            ->assertJson(['message' => 'pong']);

        $this->assertFalse($launcher->launched);
    }

    public function test_push_to_a_non_configured_branch_is_ignored(): void
    {
        config(['deployer.webhook_branch' => 'production']);
        $launcher = $this->fakeLauncher();

        $this->send(['ref' => 'refs/heads/main'])->assertOk();

        $this->assertFalse($launcher->launched);
    }

    public function test_webhook_is_disabled_when_no_secret_is_set(): void
    {
        config(['deployer.github_webhook_secret' => null]);
        $launcher = $this->fakeLauncher();

        $this->send(['ref' => 'refs/heads/main'])->assertNotFound();

        $this->assertFalse($launcher->launched);
    }
}
