<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_cycles', function (Blueprint $table) {
            // Snapshotted at cycle-creation time, same reasoning as
            // monthly_price_snapshot -- a later change to the subscription's
            // own max_clothes_per_cycle shouldn't retroactively alter a
            // cycle already in progress.
            $table->unsignedInteger('max_clothes_snapshot')->default(1)->after('monthly_price_snapshot');
        });

        DB::statement('
            UPDATE subscription_cycles c
            JOIN subscriptions s ON s.id = c.subscription_id
            SET c.max_clothes_snapshot = s.max_clothes_per_cycle
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_cycles', function (Blueprint $table) {
            $table->dropColumn('max_clothes_snapshot');
        });
    }
};
