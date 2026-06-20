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
    public function __construct(
        protected ?string $name,
        protected ?string $user,
        protected ?string $password,
        protected string $host = '127.0.0.1',
        protected string $port = '3306',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('deployer.db_name'),
            config('deployer.db_user'),
            config('deployer.db_password'),
            config('deployer.db_host', '127.0.0.1'),
            config('deployer.db_port', '3306'),
        );
    }

    public function isConfigured(): bool
    {
        return ! empty($this->name) && ! empty($this->user);
    }

    /**
     * Dump the configured database to $targetPath. Returns true on success.
     */
    public function dump(string $targetPath): bool
    {
        return $this->run('mysqldump', $targetPath, append: true);
    }

    /**
     * Restore the configured database from $sourcePath. Returns true on success.
     */
    public function restore(string $sourcePath): bool
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException("SQL file not found: {$sourcePath}");
        }

        return $this->run('mysql', $sourcePath, append: false);
    }

    /**
     * Run a mysql-family client, streaming a file in or out via redirection.
     */
    protected function run(string $binary, string $file, bool $append): bool
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Database credentials are not configured.');
        }

        $optionFile = $this->writeOptionFile();

        try {
            $redirect = $append ? '>' : '<';
            // Credentials come from the option file; only trusted/validated
            // values are interpolated and every one is shell-escaped.
            $command = sprintf(
                '%s --defaults-extra-file=%s %s %s %s',
                $binary,
                escapeshellarg($optionFile),
                escapeshellarg($this->name),
                $redirect,
                escapeshellarg($file),
            );

            $process = Process::fromShellCommandline($command);
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
