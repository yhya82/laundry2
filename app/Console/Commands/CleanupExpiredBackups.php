<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Retention comes from Setting::get('backup.retention_days') at the moment
 * each backup was created -- baked into that row's own expires_at rather
 * than recomputed here, so changing the setting later never silently deletes
 * (or keeps) backups that were made under a different retention promise.
 */
class CleanupExpiredBackups extends Command
{
    protected $signature = 'backup:cleanup';

    protected $description = 'Delete backups (from disk and the backups table) past their retention period.';

    public function handle(): int
    {
        $expired = Backup::where('status', 'completed')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired backups.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($expired as $backup) {
            try {
                Storage::disk($backup->disk)->delete('backups/'.$backup->filename);
                $backup->delete();
                $deleted++;
            } catch (\Throwable $e) {
                $this->error("Could not delete {$backup->filename}: {$e->getMessage()}");
                Log::channel('stack')->error('backup:cleanup: '.$e->getMessage());
            }
        }

        $this->info("Deleted {$deleted} of {$expired->count()} expired backup(s).");

        return self::SUCCESS;
    }
}
