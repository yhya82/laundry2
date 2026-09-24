<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 'skipped' was only ever set in one place -- SubscriptionController::
     * cancel(), flipping every still-open collection when the whole
     * subscription is cancelled. That's a distinct reason from
     * 'cancelled' (an individual collection skipped/combined into another
     * one, with its own cancellation_reason -- see CollectionController::
     * cancel()), so it gets its own explicit status instead of the vague
     * 'skipped' label.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE collections MODIFY status ENUM('scheduled', 'collected', 'skipped', 'cancelled', 'subscription_cancelled') NOT NULL DEFAULT 'scheduled'");

        DB::statement("UPDATE collections SET status = 'subscription_cancelled' WHERE status = 'skipped'");

        DB::statement("ALTER TABLE collections MODIFY status ENUM('scheduled', 'collected', 'cancelled', 'subscription_cancelled') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE collections MODIFY status ENUM('scheduled', 'collected', 'skipped', 'cancelled', 'subscription_cancelled') NOT NULL DEFAULT 'scheduled'");

        DB::statement("UPDATE collections SET status = 'skipped' WHERE status = 'subscription_cancelled'");

        DB::statement("ALTER TABLE collections MODIFY status ENUM('scheduled', 'collected', 'skipped', 'cancelled') NOT NULL DEFAULT 'scheduled'");
    }
};
