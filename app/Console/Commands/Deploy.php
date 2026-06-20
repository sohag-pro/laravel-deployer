<?php

namespace App\Console\Commands;

use App\Support\Database;
use App\Support\DeployState;
use App\Support\HealthCheck;
use App\Support\Releases;
use App\Support\Retention;
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
    protected $signature = 'deploy {--ref= : Branch, tag or commit to deploy (defaults to the repository default branch)}';

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
        $tag = now()->format('Y-m-d__H_i_s');

        // Reuse the run already marked by the dashboard, or start one when the
        // command is invoked directly from the CLI.
        $id = DeployState::running() ? (DeployState::status()['id'] ?? $tag) : DeployState::markStarted($tag);

        try {
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
                DeployState::markFinished($id, false, 'GIT_REMOTE_URL is not configured.');

                return self::FAILURE;
            }

            // Note: $serve_dir is intentionally not pre-created — it is managed
            // as a symlink by Releases::switch().
            foreach ([$base_dir, $version_dir, $storage_dir, $db_dir] as $dir) {
                $this->ensureDirectory($dir);
            }

            // Resolve the git ref to deploy (defaults to the remote's HEAD).
            $ref = trim((string) $this->option('ref'));
            if ($ref !== '' && ! self::isValidRef($ref)) {
                $this->error("Invalid git ref: {$ref}");
                DeployState::markFinished($id, false, "Invalid git ref: {$ref}");

                return self::FAILURE;
            }

            // Clone the repository into the new release directory.
            $this->exec(sprintf('git clone %s %s', escapeshellarg($git_remote_url), escapeshellarg($version_dir)));

            // Check out the requested ref (branch, tag or commit).
            if ($ref !== '') {
                $this->info("Deploying ref: {$ref}");
                $this->exec(sprintf('git -C %s checkout --quiet %s', escapeshellarg($version_dir), escapeshellarg($ref)));
            }

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

            // Remember the live release so we can roll back if the new one is
            // unhealthy, then atomically switch to the new release.
            $releasesRoot = $base_dir.config('deployer.version_dir');
            $previousLive = Releases::current($serve_dir);
            Releases::switch($serve_dir, $version_dir);

            // First-deploy-only commands (e.g. key:generate).
            if ($run_one_time_commands && ! empty($one_time_commands)) {
                $this->exec(sprintf('cd %s && %s', escapeshellarg($version_dir), $one_time_commands));
            }

            // Post-deploy health check; roll back to the previous release on
            // failure so a broken build never stays live.
            $healthUrl = (string) config('deployer.health_url');
            if ($healthUrl !== '') {
                $this->info("Health check: {$healthUrl}");
                $healthy = HealthCheck::passes(
                    $healthUrl,
                    (int) config('deployer.health_retries', 5),
                    (int) config('deployer.health_delay', 3),
                );

                if (! $healthy) {
                    $rolledBack = $this->rollback($serve_dir, $releasesRoot, $previousLive);
                    $message = $rolledBack
                        ? "Health check failed; rolled back to {$previousLive}."
                        : 'Health check failed; no previous release to roll back to.';
                    $this->error($message);
                    DeployState::markFinished($id, false, $message);

                    return self::FAILURE;
                }

                $this->info('Health check passed.');
            }

            // Prune old releases and dumps, never touching the live release.
            $prunedReleases = Retention::pruneReleases($releasesRoot, (int) config('deployer.keep_releases'), $tag);
            $prunedDumps = Retention::pruneDumps($db_dir, (int) config('deployer.keep_db_dumps'));
            if ($prunedReleases || $prunedDumps) {
                $this->info('Pruned '.count($prunedReleases).' release(s) and '.count($prunedDumps).' dump(s).');
            }

            $this->info("Deployed release {$tag}.");
            DeployState::markFinished($id, true, "Deployed release {$tag}.");

            return self::SUCCESS;
        } catch (Throwable $th) {
            Log::error('Deployment failed', ['exception' => $th]);
            $this->error($th->getMessage());
            DeployState::markFinished($id, false, $th->getMessage());

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
     * Allow only safe git ref characters (branch/tag/commit), no shell
     * metacharacters, spaces, leading dashes or '..'.
     */
    public static function isValidRef(string $ref): bool
    {
        return $ref !== ''
            && strlen($ref) <= 255
            && ! str_starts_with($ref, '-')
            && ! str_contains($ref, '..')
            && (bool) preg_match('#^[A-Za-z0-9._/-]+$#', $ref);
    }

    /**
     * Re-point the serve symlink at a previous release. Returns false when
     * there is no previous release to roll back to.
     */
    protected function rollback(string $serveDir, string $releasesRoot, ?string $previousLive): bool
    {
        if (empty($previousLive)) {
            return false;
        }

        $previousDir = rtrim($releasesRoot, '/').'/'.$previousLive;

        if (! is_dir($previousDir)) {
            return false;
        }

        Releases::switch($serveDir, $previousDir);

        return true;
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
