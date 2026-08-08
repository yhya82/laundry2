<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Runs every five minutes (see bootstrap/app.php). Table growth is logged
 * for trend visibility, not alerted on -- a laundry business growing its
 * orders table is the point, not a symptom. Slow queries, stuck
 * transactions, and the acute HealthCheckController failures (DB down,
 * pending migrations, disk nearly full) are what actually page someone.
 *
 * Each distinct problem is throttled to one email per hour (see
 * shouldAlert()) so a sustained outage sends one page, not one every five
 * minutes for as long as it lasts.
 */
class MonitorHealth extends Command
{
    protected $signature = 'health:monitor';

    protected $description = 'Check slow queries, lock waits, table growth, and the core health checks; email on new problems.';

    protected array $problems = [];

    public function handle(): int
    {
        $this->checkCoreHealth();
        $this->checkSlowQueries();
        $this->checkLockWaits();
        $this->logTableGrowth();

        if (! empty($this->problems)) {
            $this->sendAlertEmail(implode("\n\n", $this->problems));
        } else {
            $this->info('All checks passed.');
        }

        return self::SUCCESS;
    }

    protected function checkCoreHealth(): void
    {
        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            $this->flag('database', 'Database is unreachable: '.$e->getMessage());

            return;
        }

        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if ($free !== false && $total !== false && $total > 0) {
            $freePercent = round(($free / $total) * 100, 1);
            if ($freePercent < 10.0) {
                $this->flag('disk', "Disk free space is at {$freePercent}% (threshold: 10%).");
            }
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = collect(glob(database_path('migrations/*.php')))->map(fn ($p) => basename($p, '.php'))->all();
        $pending = count(array_diff($files, $ran));
        if ($pending > 0) {
            $this->flag('migrations', "{$pending} migration(s) have not been run.");
        }
    }

    /**
     * Uses the global Slow_queries status counter (cumulative since the
     * server started, or since FLUSH STATUS) rather than parsing the slow
     * query log file directly -- works regardless of whether the log is
     * writing to a file, a table, or is off (in which case this reads 0 and
     * says so, rather than failing).
     */
    protected function checkSlowQueries(): void
    {
        if (config('database.default') !== 'mysql') {
            return;
        }

        try {
            $row = DB::selectOne("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
            $current = (int) ($row->Value ?? 0);
        } catch (\Throwable $e) {
            return;
        }

        $previous = (int) Cache::get('health:slow_queries_baseline', $current);
        Cache::put('health:slow_queries_baseline', $current, now()->addDay());

        $delta = $current - $previous;

        if ($delta > 10) {
            $this->flag('slow_queries', "{$delta} new slow queries since the last check (5 min ago).");
        }
    }

    /**
     * A transaction that's been open for a while, holding row locks, is
     * exactly the kind of thing that turns into a customer-visible timeout
     * on an unrelated request -- InnoDB's own trx table is the authoritative
     * source for "how long has this actually been running," not a guess.
     */
    protected function checkLockWaits(): void
    {
        if (config('database.default') !== 'mysql') {
            return;
        }

        try {
            $stuck = DB::select("
                SELECT trx_id, trx_started, TIMESTAMPDIFF(SECOND, trx_started, NOW()) AS seconds_open
                FROM information_schema.innodb_trx
                WHERE TIMESTAMPDIFF(SECOND, trx_started, NOW()) > 30
            ");
        } catch (\Throwable $e) {
            // information_schema.innodb_trx isn't available on every
            // MySQL-compatible target (e.g. some managed/read-replica
            // setups restrict it) -- treated as "can't check," not a failure.
            return;
        }

        if (count($stuck) > 0) {
            $longest = collect($stuck)->max('seconds_open');
            $this->flag('lock_waits', count($stuck)." transaction(s) open longer than 30s (longest: {$longest}s).");
        }
    }

    protected function logTableGrowth(): void
    {
        $tables = ['orders', 'payments', 'customers', 'subscriptions', 'collections', 'activity_log'];

        $counts = [];
        foreach ($tables as $table) {
            try {
                $counts[$table] = DB::table($table)->count();
            } catch (\Throwable $e) {
                continue;
            }
        }

        Log::channel('stack')->info('health:monitor table snapshot', $counts);
    }

    protected function flag(string $key, string $message): void
    {
        $this->warn($message);

        if ($this->shouldAlert($key)) {
            $this->problems[] = $message;
        }
    }

    protected function shouldAlert(string $key): bool
    {
        $cacheKey = 'health:alerted:'.$key;

        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::put($cacheKey, true, now()->addHour());

        return true;
    }

    protected function sendAlertEmail(string $message): void
    {
        Log::channel('stack')->warning('health:monitor problems detected: '.$message);

        $to = Setting::get('backup.alert_email');

        if (! $to) {
            return;
        }

        try {
            Mail::raw("Health check found problems on ".config('app.name').":\n\n{$message}", function ($mail) use ($to) {
                $mail->to($to)->subject('[ABC Laundry] Health check alert');
            });
        } catch (\Throwable $e) {
            Log::channel('stack')->error('health:monitor: failed to send alert email: '.$e->getMessage());
        }
    }
}
