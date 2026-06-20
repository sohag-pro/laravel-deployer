<?php

namespace Tests\Feature;

use App\Support\DeployState;
use App\Support\Releases;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * End-to-end deploy orchestration against a throwaway local git repository,
 * so no network, composer or database is involved. The build step is stubbed
 * with `true` and database backups are disabled.
 */
class DeployCommandTest extends TestCase
{
    protected string $base;

    protected string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/deployer_cmd_'.uniqid();
        $this->repo = $this->base.'/src';
        mkdir($this->repo, 0755, true);

        // Build a minimal source repository with one commit.
        $this->git('init -q -b main');
        $this->git('config user.email t@t.test');
        $this->git('config user.name Test');
        mkdir($this->repo.'/storage', 0755, true);
        file_put_contents($this->repo.'/storage/.gitkeep', '');
        file_put_contents($this->repo.'/.env.example', "APP_KEY=\n");
        file_put_contents($this->repo.'/index.php', "<?php // v1\n");
        $this->git('add -A');
        $this->git('commit -q -m initial');
        $this->git('tag v1.0.0');

        config([
            'deployer.base_dir' => $this->base.'/work/',
            'deployer.version_dir' => 'releases',
            'deployer.storage_dir' => 'storage',
            'deployer.db_dir' => 'db',
            'deployer.serve_dir' => $this->base.'/work/current',
            'deployer.git_remote_url' => $this->repo,
            'deployer.commands' => 'true',
            'deployer.after_deploy' => '',
            'deployer.one_time_commands' => 'true',
            'deployer.db_name' => null,
            'deployer.health_url' => null,
            'deployer.keep_releases' => 0,
            'deployer.keep_db_dumps' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        @exec('rm -rf '.escapeshellarg($this->base));
        parent::tearDown();
    }

    protected function git(string $args): void
    {
        exec('git -C '.escapeshellarg($this->repo).' '.$args.' 2>/dev/null');
    }

    public function test_deploy_clones_builds_and_switches_the_symlink(): void
    {
        $this->assertSame(0, Artisan::call('deploy'));

        $serve = $this->base.'/work/current';
        $this->assertTrue(is_link($serve));

        // Serve points at a release whose files came from the repo.
        $this->assertFileExists($serve.'/index.php');
        $this->assertFileExists($this->base.'/work/.env');           // shared .env bootstrapped
        $this->assertSame(DeployState::SUCCESS, DeployState::status()['status']);
    }

    public function test_deploy_checks_out_a_given_ref(): void
    {
        $this->assertSame(0, Artisan::call('deploy', ['--ref' => 'v1.0.0']));

        $this->assertSame(DeployState::SUCCESS, DeployState::status()['status']);
        $this->assertNotNull(Releases::current($this->base.'/work/current'));
    }

    public function test_deploy_rejects_an_invalid_ref(): void
    {
        $this->assertSame(1, Artisan::call('deploy', ['--ref' => 'foo;rm -rf /']));
        $this->assertSame(DeployState::FAILED, DeployState::status()['status']);
    }

    public function test_deploy_is_marked_failed_when_the_clone_fails(): void
    {
        config(['deployer.git_remote_url' => $this->base.'/does-not-exist']);

        $this->assertSame(1, Artisan::call('deploy'));
        $this->assertSame(DeployState::FAILED, DeployState::status()['status']);
    }
}
