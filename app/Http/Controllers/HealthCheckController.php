<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    /**
     * Distinct from checks that only prove the app *booted* -- these are the
     * things that can fail while PHP itself is still running fine: the DB
     * connection dropping, migrations left un-run after a deploy, the queue
     * backing up, or the disk filling. A monitoring tool polling this on an
     * interval is what MonitorHealth (the scheduled command) also uses to
     * decide when to actually page someone -- this endpoint is the read,
     * that command is the alert.
     */
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'migrations' => $this->checkMigrations(),
            'queue' => $this->checkQueue(),
            'cache' => $this->checkCache(),
            'disk' => $this->checkDisk(),
        ];

        $critical = ['database', 'migrations', 'cache'];
        $healthy = collect($checks)->only($critical)->every(fn ($c) => $c['ok']);

        return response()->json([
            'status' => $healthy ? 'ok' : 'unhealthy',
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');

            return ['ok' => true, 'latency_ms' => round((microtime(true) - $start) * 1000, 1)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'unreachable'];
        }
    }

    protected function checkMigrations(): array
    {
        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = collect(glob(database_path('migrations/*.php')))
                ->map(fn ($path) => basename($path, '.php'))
                ->all();

            $pending = array_values(array_diff($files, $ran));

            return ['ok' => empty($pending), 'pending_count' => count($pending)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'could not read migrations table'];
        }
    }

    protected function checkQueue(): array
    {
        if (config('queue.default') !== 'database') {
            return ['ok' => true, 'driver' => config('queue.default')];
        }

        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            // A large backlog isn't "down", but it's exactly the kind of
            // thing that should surface here rather than be discovered when
            // a customer asks why their SMS never sent.
            return ['ok' => true, 'pending' => $pending, 'failed' => $failed];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'could not read queue tables'];
        }
    }

    protected function checkCache(): array
    {
        try {
            $key = 'health-check-'.uniqid();
            Cache::put($key, true, 5);
            $ok = Cache::get($key) === true;
            Cache::forget($key);

            return ['ok' => $ok];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'cache store unreachable'];
        }
    }

    protected function checkDisk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if ($free === false || $total === false || $total === 0) {
            return ['ok' => true, 'note' => 'could not read disk usage on this platform'];
        }

        $freePercent = round(($free / $total) * 100, 1);

        return ['ok' => $freePercent >= 10.0, 'free_percent' => $freePercent];
    }
}
