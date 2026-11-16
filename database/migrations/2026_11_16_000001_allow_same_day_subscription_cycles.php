<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A renewal may now start on the same day the previous cycle started,
     * as long as that cycle is exhausted (enforced in
     * CollectionScheduler::renew()). So (subscription_id, starts_on) can't
     * be unique anymore. The "current cycle" is now chosen by id, not
     * starts_on.
     *
     * The plain index is added before the unique one is dropped. MySQL uses
     * the unique index to back the subscription_id foreign key, and refuses
     * to drop it while no other index starts with that column.
     */
    public function up(): void
    {
        Schema::table('subscription_cycles', function (Blueprint $table) {
            $table->index(['subscription_id', 'starts_on'], 'subscription_cycles_subscription_id_starts_on_index');
        });

        Schema::table('subscription_cycles', function (Blueprint $table) {
            $table->dropUnique('subscription_cycles_subscription_id_starts_on_unique');
        });
    }

    /**
     * Fails if same-day cycles already exist -- they'd have to be resolved
     * by hand before the unique key can come back.
     */
    public function down(): void
    {
        Schema::table('subscription_cycles', function (Blueprint $table) {
            $table->unique(['subscription_id', 'starts_on'], 'subscription_cycles_subscription_id_starts_on_unique');
        });

        Schema::table('subscription_cycles', function (Blueprint $table) {
            $table->dropIndex('subscription_cycles_subscription_id_starts_on_index');
        });
    }
};
