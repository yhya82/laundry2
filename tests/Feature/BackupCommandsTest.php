<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_run_fails_gracefully_without_a_configured_bucket(): void
    {
        config(['filesystems.disks.s3.bucket' => null]);

        $exitCode = Artisan::call('backup:run');

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseHas('backups', ['status' => 'failed']);
        $this->assertDatabaseMissing('backups', ['status' => 'completed']);
    }

    public function test_backup_run_uploads_and_records_a_completed_backup(): void
    {
        Storage::fake('s3');
        config(['filesystems.disks.s3.bucket' => 'test-bucket']);
        Setting::set('backup.retention_days', '30', 'backup', 'integer');

        $exitCode = Artisan::call('backup:run');

        $this->assertSame(0, $exitCode);

        $backup = Backup::where('status', 'completed')->first();
        $this->assertNotNull($backup, 'Expected a completed backup row.');
        $this->assertGreaterThan(0, $backup->size_bytes);
        $this->assertTrue(Storage::disk('s3')->exists('backups/'.$backup->filename));
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $backup->expires_at->timestamp, 5);

        // No leftover temp dump files.
        $tmpFiles = glob(storage_path('app/backup-tmp/*'));
        $this->assertEmpty($tmpFiles, 'Expected no leftover backup temp files: '.implode(', ', $tmpFiles ?: []));
    }

    public function test_backup_cleanup_deletes_only_expired_backups(): void
    {
        Storage::fake('s3');

        $expired = Backup::create([
            'filename' => 'expired.sql.gz',
            'disk' => 's3',
            'size_bytes' => 100,
            'status' => 'completed',
            'expires_at' => now()->subDay(),
        ]);
        $current = Backup::create([
            'filename' => 'current.sql.gz',
            'disk' => 's3',
            'size_bytes' => 100,
            'status' => 'completed',
            'expires_at' => now()->addDay(),
        ]);
        Storage::disk('s3')->put('backups/expired.sql.gz', 'x');
        Storage::disk('s3')->put('backups/current.sql.gz', 'x');

        $exitCode = Artisan::call('backup:cleanup');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('backups', ['id' => $expired->id]);
        $this->assertDatabaseHas('backups', ['id' => $current->id]);
        Storage::disk('s3')->assertMissing('backups/expired.sql.gz');
        Storage::disk('s3')->assertExists('backups/current.sql.gz');
    }

    public function test_backup_restore_aborts_when_confirmation_does_not_match(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('backups/test.sql.gz', gzencode('SELECT 1;'));
        $backup = Backup::create([
            'filename' => 'test.sql.gz',
            'disk' => 's3',
            'size_bytes' => 50,
            'status' => 'completed',
        ]);

        $dbName = config('database.connections.'.config('database.default').'.database');

        $this->artisan('backup:restore', ['filename' => $backup->filename])
            ->expectsQuestion("Type the database name ({$dbName}) to confirm", 'definitely-not-that')
            ->assertExitCode(1);
    }

    public function test_backup_restore_reports_missing_backup(): void
    {
        $this->artisan('backup:restore', ['filename' => 'does-not-exist.sql.gz'])
            ->assertExitCode(1);
    }
}
