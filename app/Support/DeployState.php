<?php

namespace App\Support;

/**
 * Tracks the state of the most recent (or in-progress) deploy in two files
 * under BASE_DIR: a small JSON status file and a plain-text log. The deploy
 * runs in a detached background process, so the dashboard reads these files
 * to show live progress instead of blocking the HTTP request.
 */
class DeployState
{
    public const IDLE = 'idle';

    public const RUNNING = 'running';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    protected static function dir(): string
    {
        return rtrim((string) config('deployer.base_dir'), '/');
    }

    public static function statusPath(): string
    {
        return self::dir().'/deploy-state.json';
    }

    public static function logPath(): string
    {
        return self::dir().'/deploy.log';
    }

    /**
     * Current state as ['id', 'status', 'started_at', 'finished_at', 'message'].
     */
    public static function status(): array
    {
        $default = ['id' => null, 'status' => self::IDLE, 'started_at' => null, 'finished_at' => null, 'message' => null];

        if (! is_file(self::statusPath())) {
            return $default;
        }

        $data = json_decode((string) file_get_contents(self::statusPath()), true);

        return is_array($data) ? array_merge($default, $data) : $default;
    }

    /**
     * Whether a deploy is currently running and not yet stale.
     */
    public static function running(): bool
    {
        $status = self::status();

        if (($status['status'] ?? null) !== self::RUNNING) {
            return false;
        }

        $timeout = (int) config('deployer.deploy_timeout', 1800);
        $startedAt = (int) ($status['started_at'] ?? 0);

        return $timeout <= 0 || (time() - $startedAt) < $timeout;
    }

    /**
     * Mark a new run as started and reset the log. Returns the run id.
     */
    public static function markStarted(?string $id = null): string
    {
        $id = $id ?: date('Y-m-d__H_i_s');

        @mkdir(self::dir(), 0755, true);
        file_put_contents(self::logPath(), '');
        self::write([
            'id' => $id,
            'status' => self::RUNNING,
            'started_at' => time(),
            'finished_at' => null,
            'message' => null,
        ]);

        return $id;
    }

    public static function markFinished(string $id, bool $ok, string $message = ''): void
    {
        self::write([
            'id' => $id,
            'status' => $ok ? self::SUCCESS : self::FAILED,
            'started_at' => self::status()['started_at'] ?? null,
            'finished_at' => time(),
            'message' => $message,
        ]);
    }

    /**
     * Tail of the deploy log (last $bytes bytes).
     */
    public static function logTail(int $bytes = 40000): string
    {
        if (! is_file(self::logPath())) {
            return '';
        }

        $size = filesize(self::logPath());
        $offset = max(0, $size - $bytes);

        return (string) file_get_contents(self::logPath(), false, null, $offset);
    }

    protected static function write(array $data): void
    {
        @mkdir(self::dir(), 0755, true);
        file_put_contents(self::statusPath(), json_encode($data, JSON_PRETTY_PRINT));
    }
}
