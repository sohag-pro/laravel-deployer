<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Thin wrapper around mysqldump / mysql that keeps credentials off the
 * command line. Passing -p<password> as an argument leaks the password to
 * the process list (and shell history); instead we write a 0600 option file
 * and hand it to the client via --defaults-extra-file.
 */
class Database
{
    /**
     * mysqldump flags for a consistent, lock-light, complete backup.
     */
    protected const DUMP_FLAGS = '--single-transaction --quick --routines --triggers --no-tablespaces';

    public function __construct(
        protected ?string $name,
        protected ?string $user,
        protected ?string $password,
        protected string $host = '127.0.0.1',
        protected string $port = '3306',
        protected bool $gzip = true,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('deployer.db_name'),
            config('deployer.db_user'),
            config('deployer.db_password'),
            config('deployer.db_host', '127.0.0.1'),
            config('deployer.db_port', '3306'),
            (bool) config('deployer.gzip_dumps', true),
        );
    }

    public function isConfigured(): bool
    {
        return ! empty($this->name) && ! empty($this->user);
    }

    /**
     * Final path a dump of $targetPath would be written to (adds .gz when
     * gzip is enabled and the path is not already compressed).
     */
    public function dumpPath(string $targetPath): string
    {
        if ($this->gzip && ! str_ends_with($targetPath, '.gz')) {
            return $targetPath.'.gz';
        }

        return $targetPath;
    }

    /**
     * Dump the configured database to $targetPath (a .gz suffix is added when
     * gzip is enabled). Returns true on success.
     */
    public function dump(string $targetPath): bool
    {
        $target = $this->dumpPath($targetPath);

        $pipe = $this->gzip ? ' | gzip' : '';

        return $this->run(function (string $optionFile) use ($pipe, $target) {
            return sprintf(
                'mysqldump --defaults-extra-file=%s %s %s%s > %s',
                escapeshellarg($optionFile),
                self::DUMP_FLAGS,
                escapeshellarg($this->name),
                $pipe,
                escapeshellarg($target),
            );
        });
    }

    /**
     * Restore the configured database from $sourcePath. Gzipped dumps
     * (.gz) are decompressed on the fly. Returns true on success.
     */
    public function restore(string $sourcePath): bool
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException("SQL file not found: {$sourcePath}");
        }

        $reader = str_ends_with($sourcePath, '.gz')
            ? sprintf('gunzip -c %s', escapeshellarg($sourcePath))
            : sprintf('cat %s', escapeshellarg($sourcePath));

        return $this->run(function (string $optionFile) use ($reader) {
            return sprintf(
                '%s | mysql --defaults-extra-file=%s %s',
                $reader,
                escapeshellarg($optionFile),
                escapeshellarg($this->name),
            );
        });
    }

    /**
     * Build a shell command via $builder (given the temp option-file path),
     * run it, and clean up the credentials file afterwards.
     */
    protected function run(callable $builder): bool
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Database credentials are not configured.');
        }

        $optionFile = $this->writeOptionFile();

        try {
            $process = Process::fromShellCommandline($builder($optionFile));
            $process->setTimeout(600);
            $process->run();

            return $process->isSuccessful();
        } finally {
            @unlink($optionFile);
        }
    }

    protected function writeOptionFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'deployer_my_');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary credentials file.');
        }

        chmod($path, 0600);

        $contents = "[client]\n"
            ."user=\"{$this->user}\"\n"
            ."password=\"{$this->password}\"\n"
            ."host=\"{$this->host}\"\n"
            ."port=\"{$this->port}\"\n";

        file_put_contents($path, $contents);

        return $path;
    }
}
