<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Non-scheduled cycles have no fixed end date at creation -- it's only
 * knowable once the last collection actually resolves (see
 * SubscriptionCycle::closeIfExhausted()). Raw SQL rather than
 * Schema::table()->change() since this app doesn't depend on doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE subscription_cycles MODIFY ends_on DATE NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscription_cycles MODIFY ends_on DATE NOT NULL');
    }
};
