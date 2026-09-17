<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One-off: Gambia's numbering plan moved from 7-digit to 9-digit local
 * numbers. Remaps every existing 7-digit phone number -- whether stored as
 * +220 + 7-digit, or as a bare 7-digit local number with no country code at
 * all (the older, inconsistent format this app allowed before phone input
 * normalization was added) -- to +220 + 9-digit, prepending a 2-digit prefix
 * determined by the old number's leading digit. Runs against both users and
 * customers — each table has its own UNIQUE index on phone, a separate
 * uniqueness domain, so duplicate-detection is per table.
 */
class MigratePhoneNumbersTo9Digit extends Command
{
    protected $signature = 'pos:migrate-phone-numbers
        {--dry-run : Preview changes without writing}
        {--user= : ID of the user to attribute the activity_log entries to}';

    protected $description = 'Remaps existing 7-digit GM phone numbers (+220 + 7 digits) to the new 9-digit format (+220 + 9 digits).';

    /**
     * Leading digit of the old 7-digit local number => 2-digit prefix for
     * the new 9-digit local number. '9' (and anything else unmapped) is
     * deliberately absent — any row with an unmapped leading digit is
     * skipped and reported, not guessed at.
     */
    private const PREFIX_MAP = [
        '3' => '83', '5' => '83',
        '6' => '86', '8' => '86',
        '2' => '87', '7' => '87', '4' => '87',
    ];

    /** @var array<class-string<Model>, string> */
    private const TARGETS = [
        User::class => 'User',
        Customer::class => 'Customer',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // customers.phone updates get an activity_log row automatically,
        // from the AFTER UPDATE trigger every audited table has (see
        // 2025_01_02_000001_create_laundry_triggers.php) -- not from an
        // app-level call. That trigger reads causer_id from the
        // @current_user_id MySQL session variable, which SetCurrentUserForAudit
        // sets per HTTP request; a console command never goes through that
        // middleware, so it's set by hand here instead. users isn't one of
        // the audited tables, so this only actually matters for the
        // Customer side, but it's harmless to set regardless.
        if (! $dryRun) {
            $actorId = $this->option('user');

            if (! $actorId || ! User::whereKey($actorId)->exists()) {
                $this->error('Pass --user=<id> naming an existing user to attribute the activity_log entries to.');

                return self::FAILURE;
            }

            DB::statement('SET @current_user_id = ?', [$actorId]);
        }

        $anyFailures = false;

        foreach (self::TARGETS as $modelClass => $label) {
            if (! $this->migrateModel($modelClass, $label, $dryRun)) {
                $anyFailures = true;
            }
        }

        if ($dryRun) {
            $this->info('Dry run — no changes written.');
        }

        return $anyFailures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function migrateModel(string $modelClass, string $label, bool $dryRun): bool
    {
        $changes = [];
        $skipped = [];

        foreach ($modelClass::lazy() as $record) {
            $old = $record->phone;

            if (! $old) {
                continue; // blank
            }

            if (str_starts_with($old, '+220') && strlen($old) === 11) {
                $local = substr($old, 4); // +220 + 7-digit
            } elseif (! str_starts_with($old, '+') && strlen($old) === 7 && ctype_digit($old)) {
                $local = $old; // bare 7-digit, no country code at all
            } else {
                continue; // already 9-digit, some other format, or not a GM number
            }

            $prefix = self::PREFIX_MAP[$local[0]] ?? null;

            if ($prefix === null) {
                $skipped[] = "{$label} #{$record->id}: {$old} (leading digit '{$local[0]}' has no mapped prefix)";
                continue;
            }

            $changes[] = [$record, $old, '+220'.$prefix.$local];
        }

        $newNumbers = array_column($changes, 2);
        if (count($newNumbers) !== count(array_unique($newNumbers))) {
            $this->error("{$label}: duplicate new numbers detected — aborting this table, nothing written.");

            return false;
        }

        $this->info("{$label}: ".count($changes).' number(s) to update:');
        foreach ($changes as [$record, $old, $new]) {
            $this->line("  #{$record->id}: {$old} -> {$new}");
        }

        if ($skipped) {
            $this->warn("{$label}: ".count($skipped).' row(s) skipped:');
            foreach ($skipped as $line) {
                $this->warn("  {$line}");
            }
        }

        if ($dryRun || empty($changes)) {
            return true;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as [$record, $old, $new]) {
                $record->update(['phone' => $new]);
            }
        });

        $this->info("{$label}: ".count($changes).' record(s) updated.');

        return true;
    }
}
