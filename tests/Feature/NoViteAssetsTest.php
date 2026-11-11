<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NoViteAssetsTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/deployer-no-vite-'.uniqid();
        File::makeDirectory($this->tmp, 0755, true);

        config([
            'deployer.base_dir' => $this->tmp.'/base',
            'deployer.version_dir' => $this->tmp.'/versions',
            'deployer.serve_dir' => $this->tmp.'/serve',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    public function test_vite_toolchain_files_are_gone(): void
    {
        foreach ([
            'package.json',
            'vite.config.js',
            'resources/js/app.js',
            'resources/js/bootstrap.js',
            'resources/css/app.css',
        ] as $path) {
            $this->assertFileDoesNotExist(base_path($path));
        }
    }

    public function test_login_page_renders_without_vite_assets(): void
    {
        $this->assertRendersWithoutVite($this->get(route('login')));
    }

    public function test_home_page_renders_without_vite_assets(): void
    {
        $user = User::factory()->create();

        $this->assertRendersWithoutVite($this->actingAs($user)->get(route('home')));
    }

    public function test_two_factor_challenge_page_renders_without_vite_assets(): void
    {
        $user = User::factory()->create();

        $response = $this->withSession(['auth.2fa.user_id' => $user->id])
            ->get(route('two-factor.challenge'));

        $this->assertRendersWithoutVite($response);
    }

    public function test_two_factor_settings_page_renders_without_vite_assets(): void
    {
        $user = User::factory()->create();

        $this->assertRendersWithoutVite($this->actingAs($user)->get(route('two-factor.index')));
    }

    private function assertRendersWithoutVite(TestResponse $response): void
    {
        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString('style.css', $content);
        $this->assertStringNotContainsString('vite', strtolower($content));
        $this->assertStringNotContainsString('/build/', $content);
    }
}
