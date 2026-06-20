<?php

namespace App\Http\Controllers;

use App\Console\Commands\Deploy as DeployCommand;
use App\Support\Database;
use App\Support\DeployLauncher;
use App\Support\DeployState;
use App\Support\Releases;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DeploymentController extends Controller
{
    public function index()
    {
        $serve_dir = config('deployer.serve_dir');
        $base_dir = config('deployer.base_dir');
        $version_dir = $base_dir.config('deployer.version_dir');
        $db_dir = $base_dir.config('deployer.db_dir');
        // The currently-served release is the target of the serve symlink.
        $live = Releases::current($serve_dir);

        $this->ensureDirectory($base_dir);
        $this->ensureDirectory($version_dir);
        $this->ensureDirectory($db_dir);

        $db_files = $this->listEntries($db_dir, directoriesOnly: false);
        $folders = $this->listEntries($version_dir, directoriesOnly: true);
        $deploy = DeployState::status();

        return view('home', compact('folders', 'live', 'db_files', 'deploy'));
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

        // Atomically re-point the serve symlink at the chosen release.
        try {
            Releases::switch($serve_dir, $version_dir);
        } catch (Throwable $e) {
            Log::error('Release restore failed', ['exception' => $e]);

            return back()->with('error', 'Restore failed: '.$e->getMessage());
        }

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

    public function deploy(Request $request, DeployLauncher $launcher): RedirectResponse
    {
        if (DeployState::running()) {
            return back()->with('error', 'A deploy is already in progress.');
        }

        $ref = trim((string) $request->input('ref', ''));
        if ($ref !== '' && ! DeployCommand::isValidRef($ref)) {
            return back()->with('error', 'Invalid branch, tag or commit reference.');
        }

        // Mark running synchronously so the dashboard reflects it immediately,
        // then launch the deploy in a detached background process.
        DeployState::markStarted();
        $launcher->launch($ref);

        return back()->with('success', 'Deploy started. Watch the log for progress.');
    }

    /**
     * Live deploy status + log tail, polled by the dashboard.
     */
    public function deployStatus(): JsonResponse
    {
        $status = DeployState::status();

        return response()->json([
            'status' => $status['status'],
            'message' => $status['message'],
            'running' => DeployState::running(),
            'log' => DeployState::logTail(),
        ]);
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
