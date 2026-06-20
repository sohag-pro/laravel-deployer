<?php

namespace App\Support;

/**
 * Prunes old releases and database dumps so the deploy host does not slowly
 * fill its disk. Newest entries are kept; the currently-live release is never
 * removed regardless of the retention count.
 */
class Retention
{
    /**
     * Keep the newest $keep release directories under $versionDir (plus the
     * live release), deleting the rest along with any matching .zip archive.
     *
     * @return string[] names of the releases that were removed
     */
    public static function pruneReleases(string $versionDir, int $keep, ?string $liveName = null): array
    {
        if ($keep <= 0 || ! is_dir($versionDir)) {
            return [];
        }

        $releases = [];
        foreach (array_diff(scandir($versionDir) ?: [], ['.', '..']) as $entry) {
            if (is_dir($versionDir.'/'.$entry)) {
                $releases[] = $entry;
            }
        }

        // Newest first; timestamped names sort chronologically.
        rsort($releases);

        $removed = [];
        $kept = 0;
        foreach ($releases as $release) {
            if ($release === $liveName || $kept < $keep) {
                $kept++;

                continue;
            }

            self::delete($versionDir.'/'.$release);
            @unlink($versionDir.'/'.$release.'.zip');
            $removed[] = $release;
        }

        return $removed;
    }

    /**
     * Keep the newest $keep files under $dbDir, deleting older dumps.
     *
     * @return string[] names of the dumps that were removed
     */
    public static function pruneDumps(string $dbDir, int $keep): array
    {
        if ($keep <= 0 || ! is_dir($dbDir)) {
            return [];
        }

        $files = [];
        foreach (array_diff(scandir($dbDir) ?: [], ['.', '..']) as $entry) {
            if (is_file($dbDir.'/'.$entry)) {
                $files[] = $entry;
            }
        }

        rsort($files);

        $removed = [];
        foreach (array_slice($files, $keep) as $file) {
            @unlink($dbDir.'/'.$file);
            $removed[] = $file;
        }

        return $removed;
    }

    protected static function delete(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
