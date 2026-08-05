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
        DB::statement("ALTER TABLE collections MODIFY status ENUM('scheduled', 'collected', 'skipped', 'cancelled') NOT NULL DEFAULT 'scheduled'");

        Schema::table('collections', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('collected_at');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            // The other scheduled collection (same subscription) this one's
            // slot got folded into -- that visit then fulfills both slots
            // at once. Self-referencing, so no cascade: the target must stay
            // resolvable independently of this row's lifecycle.
            $table->foreignId('combined_into_collection_id')->nullable()->after('cancellation_reason')
                ->constrained('collections')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combined_into_collection_id');
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });

        DB::statement("ALTER TABLE collections MODIFY status ENUM('scheduled', 'collected', 'skipped') NOT NULL DEFAULT 'scheduled'");
    }
};
