<?php

namespace Litepie\FileHub\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Litepie\FileHub\Models\FileAttachment;

class CleanupOrphanedFiles extends Command
{
    protected $signature = 'filehub:cleanup-orphaned
                            {--disk= : Storage disk to clean (default: all)}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--days=7 : Files older than this many days}';

    protected $description = 'Clean up orphaned files that no longer have database records';

    public function handle(): int
    {
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');
        $disk = $this->option('disk');

        $this->info("Cleaning up orphaned files older than {$days} days...");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
        }

        $disks = $disk ? [$disk] : [config('filehub.default_disk')];
        $totalCleaned = 0;

        foreach ($disks as $diskName) {
            $cleaned = $this->cleanupDisk($diskName, $days, $dryRun);
            $totalCleaned += $cleaned;
        }

        $this->info("Cleanup completed. {$totalCleaned} files processed.");

        return 0;
    }

    private function cleanupDisk(string $disk, int $days, bool $dryRun): int
    {
        $this->info("Processing disk: {$disk}");
        
        $storage = Storage::disk($disk);
        $cutoffDate = now()->subDays($days);
        $cleaned = 0;

        // Get all file paths from database for this disk
        $dbPaths = FileAttachment::where('disk', $disk)
            ->pluck('path')
            ->merge(
                FileAttachment::where('disk', $disk)
                    ->whereNotNull('variants')
                    ->get()
                    ->flatMap(function ($attachment) {
                        return collect($attachment->variants)->pluck('path');
                    })
            )
            ->unique()
            ->toArray();

        // Get all files from storage
        $allFiles = $storage->allFiles();

        foreach ($allFiles as $filePath) {
            // Skip if file is in database
            if (in_array($filePath, $dbPaths)) {
                continue;
            }

            // Skip if file is newer than cutoff
            $lastModified = $storage->lastModified($filePath);
            if ($lastModified && $lastModified > $cutoffDate->timestamp) {
                continue;
            }

            $this->line("Orphaned file: {$filePath}");

            if (!$dryRun) {
                $storage->delete($filePath);
            }

            $cleaned++;
        }

        $this->info("Processed {$cleaned} orphaned files on disk {$disk}");

        return $cleaned;
    }
}
