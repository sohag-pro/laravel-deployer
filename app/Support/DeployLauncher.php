<?php

namespace App\Support;

use Symfony\Component\Process\Process;

/**
 * Launches `php artisan deploy` as a detached background process so the HTTP
 * request returns immediately. Output is appended to the deploy log; nohup
 * keeps the process alive after the request (and the parent shell) exits.
 *
 * Resolved from the container so tests can swap in a fake.
 */
class DeployLauncher
{
    /**
     * Start a deploy unless one is already running. Marks the run started
     * synchronously and launches it in the background. Returns false when a
     * deploy is already in progress.
     */
    public function trigger(?string $ref = null): bool
    {
        if (DeployState::running()) {
            return false;
        }

        DeployState::markStarted();
        $this->launch($ref);

        return true;
    }

    public function launch(?string $ref = null): void
    {
        $php = (string) config('deployer.php_binary', 'php');
        $artisan = base_path('artisan');
        $log = DeployState::logPath();

        $refArg = ($ref !== null && $ref !== '')
            ? ' --ref='.escapeshellarg($ref)
            : '';

        $command = sprintf(
            'nohup %s %s deploy%s >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            $refArg,
            escapeshellarg($log),
        );

        Process::fromShellCommandline($command)->run();
    }
}
