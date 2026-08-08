<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\select;

use Illuminate\Support\Facades\Storage;

/**
 * Restoring overwrites the target database wholesale -- there is no partial
 * or merge mode. The typed-confirmation guard exists because a mistyped
 * --database flag pointed at the wrong environment is exactly the kind of
 * mistake a plain y/n prompt doesn't stop (see this project's own history:
 * an unscoped --env=testing flag wiped the real dev database earlier in this
 * project's life, from a command that had no confirmation guard at all).
 */
class RestoreBackup extends Command
{
    protected $signature = 'backup:restore {filename? : Backup filename to restore; omit to pick from a list}';

    protected $description = 'Restore a backup onto the CURRENT database connection. Destructive -- requires typed confirmation.';

    public function handle(): int
    {
        $filename = $this->argument('filename') ?? $this->pickBackup();

        if (! $filename) {
            $this->error('No completed backups to restore.');

            return self::FAILURE;
        }

        $backup = Backup::where('filename', $filename)->where('status', 'completed')->first();

        if (! $backup) {
            $this->error("No completed backup found with filename '{$filename}'.");

            return self::FAILURE;
        }

        $connection = config('database.connections.'.config('database.default'));

        $this->warn('This will PERMANENTLY OVERWRITE the database below with the contents of the backup.');
        $this->line("  Target database: {$connection['database']}");
        $this->line("  Target host:     {$connection['host']}");
        $this->line("  Backup file:     {$backup->filename} (".$backup->created_at->format('Y-m-d H:i:s').")");
        $this->newLine();

        $typed = $this->ask("Type the database name ({$connection['database']}) to confirm");

        if ($typed !== $connection['database']) {
            $this->error('Confirmation did not match. Aborted -- nothing was restored.');

            return self::FAILURE;
        }

        $localGz = storage_path('app/backup-tmp/restore-'.$backup->filename);
        $localSql = str_replace('.gz', '', $localGz);

        if (! is_dir(dirname($localGz))) {
            mkdir(dirname($localGz), 0755, true);
        }

        $this->info('Downloading backup...');

        try {
            $contents = Storage::disk($backup->disk)->readStream('backups/'.$backup->filename);
            file_put_contents($localGz, stream_get_contents($contents));
        } catch (\Throwable $e) {
            $this->error('Download failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->ungzip($localGz, $localSql);
        @unlink($localGz);

        $this->info('Restoring...');

        try {
            $this->restore($connection, $localSql);
        } catch (ProcessFailedException $e) {
            @unlink($localSql);
            $this->error('mysql restore failed: '.$e->getMessage());

            return self::FAILURE;
        }

        @unlink($localSql);

        $this->info('Restore complete.');

        return self::SUCCESS;
    }

    protected function pickBackup(): ?string
    {
        $backups = Backup::where('status', 'completed')->latest('created_at')->limit(20)->get();

        if ($backups->isEmpty()) {
            return null;
        }

        return select(
            label: 'Which backup do you want to restore?',
            options: $backups->mapWithKeys(fn ($b) => [
                $b->filename => $b->filename.' ('.$b->created_at->format('Y-m-d H:i').', '.number_format($b->size_bytes / 1024 / 1024, 1).' MB)',
            ])->all(),
        );
    }

    protected function ungzip(string $source, string $destination): void
    {
        $in = gzopen($source, 'rb');
        $out = fopen($destination, 'wb');

        while (! gzeof($in)) {
            fwrite($out, gzread($in, 1024 * 512));
        }

        gzclose($in);
        fclose($out);
    }

    protected function restore(array $connection, string $sqlPath): void
    {
        $binary = env('MYSQL_PATH', 'mysql');

        $process = new Process([
            $binary,
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            $connection['database'],
        ], null, ['MYSQL_PWD' => $connection['password']]);

        $process->setInput(fopen($sqlPath, 'r'));
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
