<?php

namespace App\Console\Commands;

use App\Support\Database;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class Deploy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clone the latest commit into a fresh, timestamped release and switch the live symlink to it (zero-downtime).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $tag = now()->format('Y-m-d__H_i_s');
            $one_time_commands = config('deployer.one_time_commands');
            $commands = config('deployer.commands');
            $git_remote_url = config('deployer.git_remote_url');
            $base_dir = config('deployer.base_dir');
            $serve_dir = config('deployer.serve_dir');
            $version_dir = $base_dir.config('deployer.version_dir').'/'.$tag;
            $storage_dir = $base_dir.config('deployer.storage_dir');
            $db_dir = $base_dir.config('deployer.db_dir');

            if (empty($git_remote_url)) {
                $this->error('GIT_REMOTE_URL is not configured.');

                return self::FAILURE;
            }

            foreach ([$base_dir, $version_dir, $serve_dir, $storage_dir, $db_dir] as $dir) {
                $this->ensureDirectory($dir);
            }

            // Clone the repository into the new release directory.
            $this->exec(sprintf('git clone %s %s', escapeshellarg($git_remote_url), escapeshellarg($version_dir)));

            // Seed the shared storage directory on the very first deploy only.
            if (count(scandir($storage_dir)) === 2) {
                $this->exec(sprintf('cp -r %s/. %s', escapeshellarg($version_dir.'/storage'), escapeshellarg($storage_dir)));
            }

            // Replace the release's storage with a symlink to shared storage.
            $this->exec(sprintf('rm -rf %s', escapeshellarg($version_dir.'/storage')));
            $this->exec(sprintf('ln -s %s %s', escapeshellarg($storage_dir), escapeshellarg($version_dir)));

            // Bootstrap a shared .env from the example on the first deploy.
            $run_one_time_commands = false;
            if (! file_exists($base_dir.'/.env')) {
                $run_one_time_commands = true;
                $this->exec(sprintf('cp %s %s', escapeshellarg($version_dir.'/.env.example'), escapeshellarg($base_dir.'/.env')));

                $envPath = $base_dir.'/.env';
                file_put_contents(
                    $envPath,
                    str_replace('APP_KEY=', 'APP_KEY='.config('app.key'), file_get_contents($envPath))
                );
            }

            // Take a database backup before switching releases (best effort).
            $database = Database::fromConfig();
            if ($database->isConfigured()) {
                $database->dump($db_dir.'/'.config('deployer.db_name').'-'.$tag.'.sql');
            }

            // Link the shared .env into the release.
            $this->exec(sprintf('ln -s %s %s', escapeshellarg($base_dir.'/.env'), escapeshellarg($version_dir.'/.env')));

            // Build the release.
            $this->exec(sprintf('cd %s && %s', escapeshellarg($version_dir), $commands));

            // Run configured after-deploy commands inside the release.
            foreach (explode(',', (string) config('deployer.after_deploy')) as $command) {
                if (trim($command) !== '') {
                    $this->exec(sprintf('cd %s && %s', escapeshellarg($version_dir), trim($command)));
                }
            }

            // Run a project-provided afterDeploy.sh hook if present.
            if (file_exists("$version_dir/afterDeploy.sh")) {
                $this->exec(sprintf('cd %s && sh afterDeploy.sh', escapeshellarg($version_dir)));
            }

            // Atomically switch the live serve directory to the new release.
            $this->exec(sprintf('rm -rf %s', escapeshellarg($serve_dir).'/*'));
            $this->exec(sprintf('ln -s %s %s', escapeshellarg($version_dir).'/*', escapeshellarg($serve_dir)));

            // First-deploy-only commands (e.g. key:generate).
            if ($run_one_time_commands && ! empty($one_time_commands)) {
                $this->exec(sprintf('cd %s && %s', escapeshellarg($version_dir), $one_time_commands));
            }

            $this->info("Deployed release {$tag}.");

            return self::SUCCESS;
        } catch (Throwable $th) {
            Log::error('Deployment failed', ['exception' => $th]);
            $this->error($th->getMessage());

            return self::FAILURE;
        }
    }

    protected function ensureDirectory(string $path): void
    {
        if (! file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Run a shell command, streaming output, and abort the deploy on failure.
     */
    protected function exec(string $command): void
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(900);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("Command failed: {$command}");
        }
    }
}
