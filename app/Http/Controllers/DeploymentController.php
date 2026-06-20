<?php

namespace App\Http\Controllers;

use App\Support\Database;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;
use Throwable;

class DeploymentController extends Controller
{
    public function index()
    {
        $serve_dir = config('deployer.serve_dir');
        $base_dir = config('deployer.base_dir');
        $version_dir = $base_dir.config('deployer.version_dir');
        $db_dir = $base_dir.config('deployer.db_dir');
        $live = null;

        // Resolve the currently-served version from the symlink target.
        if (file_exists($serve_dir)) {
            $parts = @explode('/', (string) @readlink("$serve_dir/app"));
            $live = $parts[count($parts) - 2] ?? null;
        }

        $this->ensureDirectory($base_dir);
        $this->ensureDirectory($version_dir);
        $this->ensureDirectory($db_dir);

        $db_files = $this->listEntries($db_dir, directoriesOnly: false);
        $folders = $this->listEntries($version_dir, directoriesOnly: true);

        return view('home', compact('folders', 'live', 'db_files'));
    }

    public function download(string $folder): BinaryFileResponse|RedirectResponse
    {
        $folder = $this->safeName($folder);
        $version_dir = config('deployer.base_dir').config('deployer.version_dir');
        $zip_file = $version_dir.'/'.$folder.'.zip';

        if (! is_dir($version_dir.'/'.$folder)) {
            return back()->with('error', 'Version not found.');
        }

        if (! file_exists($zip_file)) {
            $this->zip($folder);
        }

        return response()->download($zip_file);
    }

    public function restore(string $folder): RedirectResponse
    {
        $folder = $this->safeName($folder);
        $serve_dir = config('deployer.serve_dir');
        $version_dir = config('deployer.base_dir').config('deployer.version_dir').'/'.$folder;

        if (! is_dir($version_dir)) {
            return back()->with('error', 'Version not found.');
        }

        if (empty($serve_dir)) {
            return back()->with('error', 'Serve directory is not configured.');
        }

        // Atomically re-point the serve directory at the chosen version.
        Process::fromShellCommandline(
            sprintf('rm -rf %s', escapeshellarg($serve_dir).'/*')
        )->mustRun();

        Process::fromShellCommandline(
            sprintf('ln -s %s %s', escapeshellarg($version_dir).'/*', escapeshellarg($serve_dir))
        )->mustRun();

        return back()->with('success', 'Restored successfully.');
    }

    public function downloadDb(string $db_file): BinaryFileResponse|RedirectResponse
    {
        $db_file = $this->safeName($db_file);
        $db_dir = config('deployer.base_dir').config('deployer.db_dir');
        $path = $db_dir.'/'.$db_file;

        if (! is_file($path)) {
            return back()->with('error', 'File not found.');
        }

        return response()->download($path);
    }

    public function restoreDb(string $db_file): RedirectResponse
    {
        $db_file = $this->safeName($db_file);
        $db_dir = config('deployer.base_dir').config('deployer.db_dir');
        $path = $db_dir.'/'.$db_file;

        if (! is_file($path)) {
            return back()->with('error', 'File not found.');
        }

        try {
            $ok = Database::fromConfig()->restore($path);
        } catch (Throwable $e) {
            Log::error('Database restore failed', ['exception' => $e]);

            return back()->with('error', 'Restore failed: '.$e->getMessage());
        }

        return $ok
            ? back()->with('success', 'Database restored successfully.')
            : back()->with('error', 'Database restore failed. Check the logs.');
    }

    public function deploy(): RedirectResponse
    {
        $exitCode = Artisan::call('deploy');

        if ($exitCode !== 0) {
            return back()->with('error', 'Deploy failed. '.trim(Artisan::output()));
        }

        return back()->with('success', 'Deployed successfully.');
    }

    /**
     * Reject any path component that is not a plain, safe file/folder name.
     * Blocks traversal (..), separators and shell metacharacters before the
     * value is ever used to build a filesystem path or a command.
     */
    protected function safeName(string $name): string
    {
        abort_unless(
            $name !== ''
                && ! in_array($name, ['.', '..'], true)
                && $name === basename($name)
                && preg_match('/^[A-Za-z0-9._-]+$/', $name),
            404
        );

        return $name;
    }

    protected function ensureDirectory(string $path): void
    {
        if (! file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * List directory entries as [name, created_at], newest first.
     */
    protected function listEntries(string $dir, bool $directoriesOnly): array
    {
        $entries = array_diff(scandir($dir) ?: [], ['.', '..']);

        $entries = array_filter($entries, function ($entry) use ($dir, $directoriesOnly) {
            return ! $directoriesOnly || is_dir($dir.'/'.$entry);
        });

        $entries = array_map(function ($entry) use ($dir) {
            return [
                'name' => $entry,
                'created_at' => date('Y-m-d H:i:s', filectime($dir.'/'.$entry)),
            ];
        }, $entries);

        usort($entries, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return array_values($entries);
    }

    protected function zip(string $folder): void
    {
        $version_dir = config('deployer.base_dir').config('deployer.version_dir');
        $zip_file = $version_dir.'/'.$folder.'.zip';

        $zip = new \ZipArchive;
        $zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($version_dir.'/'.$folder),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($version_dir.'/'.$folder) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }
}
