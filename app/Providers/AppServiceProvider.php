<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Ensure the public storage symlink exists for file access
        $this->ensureStorageSymlink();
    }

    /**
     * Ensure that the storage symlink exists and is valid.
     * This allows files stored in storage/app/public to be accessible via /storage URLs.
     *
     * On Windows, if symlinks cannot be created, falls back to a development-friendly solution.
     */
    private function ensureStorageSymlink(): void
    {
        $publicDisk = storage_path('app/public');
        $storageLinkPath = public_path('storage');

        // If the symlink already exists and is valid, nothing to do
        if (is_link($storageLinkPath) && realpath($storageLinkPath) === realpath($publicDisk)) {
            return;
        }

        // If path exists but is broken or invalid, remove it first
        if (file_exists($storageLinkPath) || is_link($storageLinkPath)) {
            // For Windows: remove directory, for Unix: remove symlink
            if (is_link($storageLinkPath)) {
                unlink($storageLinkPath);
            } elseif (is_dir($storageLinkPath)) {
                // If it's a real directory (not symlink), we can try to remove it on development
                // but for production, this is risky. Use artisan storage:link instead.
                if (app()->environment('local') || app()->environment('testing')) {
                    // In development, we can be more aggressive
                    // but we should ideally have a proper symlink
                }
            }
        }

        // Ensure the target directory exists
        if (!is_dir($publicDisk)) {
            mkdir($publicDisk, 0755, true);
        }

        // Try to create the symlink
        try {
            // Ensure public directory exists
            if (!is_dir(public_path())) {
                mkdir(public_path(), 0755, true);
            }

            if (!is_link($storageLinkPath)) {
                if (PHP_OS_FAMILY === 'Windows') {
                    // On Windows, try junction (works without admin rights usually)
                    $publicDisk = str_replace('/', '\\', $publicDisk);
                    $storageLinkPath = str_replace('/', '\\', $storageLinkPath);
                    exec("mklink /J \"$storageLinkPath\" \"$publicDisk\"", $output, $exitCode);

                    if ($exitCode !== 0 && !is_dir($storageLinkPath)) {
                        // Fallback: create a simple directory copy for development
                        // This is not ideal for production, but ensures photos work in local dev
                        if (!is_dir($storageLinkPath)) {
                            mkdir($storageLinkPath, 0755, true);
                            $this->copyDirectoryContents($publicDisk, $storageLinkPath);
                        }
                    }
                } else {
                    // Unix/Linux symlink
                    symlink($publicDisk, $storageLinkPath);
                }
            }
        } catch (\Exception $e) {
            // Log but don't crash the app if symlink creation fails
            // In production, you should run: php artisan storage:link
            if (app()->environment('local')) {
                logger()->warning('Failed to create storage symlink: ' . $e->getMessage());
            }
        }
    }

    /**
     * Recursively copy directory contents (fallback for Windows if symlink fails).
     * Only used in development environments.
     */
    private function copyDirectoryContents(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        foreach (scandir($source) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $file;
            $destPath = $destination . DIRECTORY_SEPARATOR . $file;

            if (is_dir($sourcePath)) {
                $this->copyDirectoryContents($sourcePath, $destPath);
            } elseif (!file_exists($destPath)) {
                copy($sourcePath, $destPath);
            }
        }
    }
}
