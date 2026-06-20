<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @dataProvider guardedRoutes */
    public function test_guests_cannot_reach_deployment_actions(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertRedirect(route('login'));
    }

    public static function guardedRoutes(): array
    {
        return [
            'deploy' => ['post', '/deploy'],
            'restore' => ['post', '/restore/2024'],
            'restore db' => ['post', '/restore-db/dump.sql'],
            'download' => ['get', '/download/2024'],
            'download db' => ['get', '/download-db/dump.sql'],
            'dashboard' => ['get', '/'],
        ];
    }

    public function test_destructive_actions_reject_get_requests(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/deploy')->assertStatus(405);
        $this->get('/restore/2024')->assertStatus(405);
        $this->get('/restore-db/dump.sql')->assertStatus(405);
    }

    /** @dataProvider maliciousNames */
    public function test_unsafe_filenames_are_rejected_without_touching_the_shell(string $name): void
    {
        config(['deployer.base_dir' => sys_get_temp_dir(), 'deployer.db_dir' => '/db']);

        $this->actingAs(User::factory()->create())
            ->post('/restore-db/'.$name)
            ->assertNotFound();
    }

    public static function maliciousNames(): array
    {
        return [
            'semicolon' => ['x;rm'],
            'backtick' => ['x`id`'],
            'pipe' => ['x|whoami'],
            'dollar' => ['x$(id)'],
            'dotdot' => ['..'],
        ];
    }

    public function test_restore_db_reports_missing_file_for_safe_name(): void
    {
        config(['deployer.base_dir' => sys_get_temp_dir(), 'deployer.db_dir' => '/db']);

        $this->actingAs(User::factory()->create())
            ->post('/restore-db/does-not-exist.sql')
            ->assertSessionHas('error');
    }
}
