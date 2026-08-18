<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class EnsureStorageLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:ensure-link {--refresh : Remove and recreate the symlink}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure the public storage symlink exists and is valid (works on Windows and Unix)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $publicDisk = storage_path('app/public');
        $storageLinkPath = public_path('storage');

        // Validate source directory exists
        if (!is_dir($publicDisk)) {
            $this->error("Source directory does not exist: {$publicDisk}");
            return 1;
        }

        // Ensure public directory exists
        if (!is_dir(public_path())) {
            mkdir(public_path(), 0755, true);
            $this->info('Created public directory');
        }

        // Remove existing symlink if --refresh option is used
        if ($this->option('refresh')) {
            if (file_exists($storageLinkPath) || is_link($storageLinkPath)) {
                if (is_link($storageLinkPath)) {
                    unlink($storageLinkPath);
                    $this->info('Removed existing symlink');
                } elseif (is_dir($storageLinkPath) && count(scandir($storageLinkPath)) === 2) {
                    // Only remove empty directories
                    rmdir($storageLinkPath);
                    $this->info('Removed empty storage directory');
                } else {
                    $this->warn('Could not remove existing storage path (not a symlink or not empty)');
                }
            }
        }

        // Check if symlink already exists and is valid
        if (is_link($storageLinkPath) && realpath($storageLinkPath) === realpath($publicDisk)) {
            $this->info('✓ Storage symlink is valid and working');
            return 0;
        }

        // If path exists but is broken, show warning
        if (file_exists($storageLinkPath) || is_link($storageLinkPath)) {
            if (is_link($storageLinkPath)) {
                $this->warn('Symlink exists but is broken or invalid');
                unlink($storageLinkPath);
            } else {
                $this->warn('Directory exists at storage path but is not a symlink');
                $this->warn('For production: run "php artisan storage:link" or delete ' . $storageLinkPath);
                return 1;
            }
        }

        // Create the symlink
        try {
            $this->createSymlink($publicDisk, $storageLinkPath);
            $this->info('✓ Storage symlink created successfully');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to create symlink: ' . $e->getMessage());
            $this->error('');
            $this->error('For production deployment:');
            $this->error('  1. Use: php artisan storage:link');
            $this->error('  2. Or manually create a symlink:');
            if (PHP_OS_FAMILY === 'Windows') {
                $this->error("     mklink /J \"" . str_replace('/', '\\', $storageLinkPath) . "\" \"" . str_replace('/', '\\', $publicDisk) . "\"");
            } else {
                $this->error("     ln -s \"$publicDisk\" \"$storageLinkPath\"");
            }
            return 1;
        }
    }

    /**
     * Create a symlink with platform-specific handling.
     */
    private function createSymlink(string $source, string $link): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $source = str_replace('/', '\\', $source);
            $link = str_replace('/', '\\', $link);

            // Try junction (works without admin rights usually on Windows)
            exec("mklink /J \"$link\" \"$source\"", $output, $exitCode);

            if ($exitCode !== 0) {
                throw new \Exception('Failed to create junction on Windows. Exit code: ' . $exitCode);
            }
        } else {
            // Unix/Linux symlink
            if (!symlink($source, $link)) {
                throw new \Exception('Failed to create symlink on Unix/Linux');
            }
        }
    }
}
