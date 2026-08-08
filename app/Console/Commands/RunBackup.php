<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * A `backups` row is only ever written after the upload itself has actually
 * succeeded (or, for a failure, after we know for certain it didn't) --
 * never speculatively beforehand. That ordering is the whole point of this
 * command, not an implementation detail: a row that exists but points at
 * nothing on the disk would be worse than no row at all.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Dump the database, upload it to the configured backup disk, and record the result.';

    public function handle(): int
    {
        $disk = 's3';
        $connection = config('database.connections.'.config('database.default'));
        $filename = sprintf('%s_%s.sql.gz', config('database.connections.'.config('database.default').'.database'), now()->format('Y-m-d_His'));
        $localPath = storage_path('app/backup-tmp/'.$filename);

        if (! is_dir(dirname($localPath))) {
            mkdir(dirname($localPath), 0755, true);
        }

        if (! config('filesystems.disks.'.$disk.'.bucket')) {
            return $this->failBackup('No backup bucket configured (AWS_BUCKET is empty) -- nothing was dumped.');
        }

        $dumpPath = str_replace('.gz', '', $localPath);

        try {
            $this->dump($connection, $dumpPath);
        } catch (ProcessFailedException $e) {
            @unlink($dumpPath);

            return $this->failBackup('mysqldump failed: '.Str::limit($e->getMessage(), 300));
        }

        $this->gzip($dumpPath, $localPath);
        @unlink($dumpPath);

        $sizeBytes = filesize($localPath);

        try {
            $stream = fopen($localPath, 'r');
            $uploaded = Storage::disk($disk)->put('backups/'.$filename, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Throwable $e) {
            @unlink($localPath);

            return $this->failBackup('Upload to '.$disk.' failed: '.Str::limit($e->getMessage(), 300));
        }

        @unlink($localPath);

        if (! $uploaded) {
            return $this->failBackup('Upload to '.$disk.' returned failure with no exception.');
        }

        $retentionDays = (int) (Setting::get('backup.retention_days') ?? 30);

        Backup::create([
            'filename' => $filename,
            'disk' => $disk,
            'size_bytes' => $sizeBytes,
            'status' => 'completed',
            'expires_at' => now()->addDays($retentionDays),
        ]);

        $this->info("Backup uploaded: {$filename} ({$sizeBytes} bytes).");

        return self::SUCCESS;
    }

    protected function dump(array $connection, string $dumpPath): void
    {
        $binary = env('MYSQLDUMP_PATH', 'mysqldump');

        $process = new Process([
            $binary,
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            '--single-transaction',
            '--quick',
            '--result-file='.$dumpPath,
            $connection['database'],
        ], null, ['MYSQL_PWD' => $connection['password']]);

        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    protected function gzip(string $source, string $destination): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($destination, 'wb9');

        while (! feof($in)) {
            gzwrite($out, fread($in, 1024 * 512));
        }

        fclose($in);
        gzclose($out);
    }

    /**
     * A failed backup is still recorded -- a missing row would just look like
     * the schedule silently didn't run, which is a worse failure mode than a
     * visible status='failed' row plus an email.
     */
    protected function failBackup(string $message): int
    {
        $this->error($message);
        Log::channel('stack')->error('backup:run failed: '.$message);

        Backup::create([
            'filename' => '(failed '.now()->format('Y-m-d_His').')',
            'disk' => 's3',
            'status' => 'failed',
        ]);

        $this->sendAlertEmail($message);

        return self::FAILURE;
    }

    protected function sendAlertEmail(string $message): void
    {
        $to = Setting::get('backup.alert_email');

        if (! $to) {
            return;
        }

        try {
            Mail::raw("Backup failed on ".config('app.name').":\n\n{$message}", function ($mail) use ($to) {
                $mail->to($to)->subject('[ABC Laundry] Backup failed');
            });
        } catch (\Throwable $e) {
            Log::channel('stack')->error('backup:run: failed to send failure alert email: '.$e->getMessage());
        }
    }
}
