<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Non-scheduled subscriptions create cards with no date at all --
        // the unique(subscription_id, scheduled_date) index is unaffected,
        // since MySQL treats every NULL in a unique index as distinct from
        // every other NULL.
        DB::statement('ALTER TABLE collections MODIFY scheduled_date DATE NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE collections MODIFY scheduled_date DATE NOT NULL');
    }
};
