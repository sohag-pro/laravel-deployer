<?php

namespace App\Support;

use RuntimeException;

/**
 * Manages the "current release" symlink that the web server's document root
 * follows. Switching releases is a single atomic rename of a symlink, so a
 * request never sees a half-built or missing release (true zero-downtime).
 *
 * SERVE_DIR is itself a symlink that points at the active release directory.
 * Point your web server's root at SERVE_DIR/public (it resolves through the
 * symlink to the live release).
 */
class Releases
{
    /**
     * Atomically point the serve path at $releaseDir.
     *
     * A temporary symlink is created next to the serve path and then renamed
     * over it; rename(2) is atomic on the same filesystem and replaces an
     * existing symlink in place. A legacy real directory at the serve path
     * (from the pre-symlink model) is removed once, on first switch.
     */
    public static function switch(string $serveDir, string $releaseDir): void
    {
        $serveDir = rtrim($serveDir, '/');
        $releaseDir = rtrim($releaseDir, '/');

        if (! is_dir($releaseDir)) {
            throw new RuntimeException("Release directory does not exist: {$releaseDir}");
        }

        // One-time migration: a real directory cannot be atomically replaced
        // by rename(), so remove it before linking. Symlinks are replaced in
        // place and need no special handling.
        if (file_exists($serveDir) && ! is_link($serveDir) && is_dir($serveDir)) {
            self::removeDirectory($serveDir);
        }

        $tmp = $serveDir.'__switch_tmp';
        if (is_link($tmp) || file_exists($tmp)) {
            @unlink($tmp);
        }

        if (! @symlink($releaseDir, $tmp)) {
            throw new RuntimeException("Failed to create release symlink at {$tmp}");
        }

        if (! @rename($tmp, $serveDir)) {
            @unlink($tmp);
            throw new RuntimeException("Failed to switch serve directory to {$releaseDir}");
        }
    }

    /**
     * Name of the release the serve path currently points at, or null.
     */
    public static function current(string $serveDir): ?string
    {
        $serveDir = rtrim($serveDir, '/');

        if (! is_link($serveDir)) {
            return null;
        }

        $target = @readlink($serveDir);

        return $target ? basename($target) : null;
    }

    /**
     * Recursively delete a directory (used only for the one-time migration of
     * a legacy non-symlink serve directory).
     */
    protected static function removeDirectory(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
