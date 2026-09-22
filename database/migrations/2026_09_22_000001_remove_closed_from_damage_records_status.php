<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 'closed' added no information a client couldn't already see from
     * status alone plus whether a resolution exists -- it was reachable
     * from both 'resolved' and 'rejected', so "closed" never said which one
     * actually happened. Dropping it: 'resolved' and 'rejected' become
     * terminal directly.
     */
    public function up(): void
    {
        // Reclassify existing 'closed' rows back to what they were before
        // closing -- resolved if a damage_resolutions row exists, rejected
        // otherwise. trg_damage_records_resolve_guard blocks setting status
        // to 'resolved' directly, the same way trg_damage_resolutions_apply_status
        // itself bypasses it internally.
        DB::statement('SET @allow_damage_resolve = 1');
        DB::statement("
            UPDATE damage_records dr
            LEFT JOIN damage_resolutions res ON res.damage_record_id = dr.id
            SET dr.status = IF(res.id IS NOT NULL, 'resolved', 'rejected')
            WHERE dr.status = 'closed'
        ");
        DB::statement('SET @allow_damage_resolve = NULL');

        DB::statement("ALTER TABLE damage_records MODIFY status ENUM('pending_review', 'under_investigation', 'approved', 'rejected', 'resolved') DEFAULT 'pending_review'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE damage_records MODIFY status ENUM('pending_review', 'under_investigation', 'approved', 'rejected', 'resolved', 'closed') DEFAULT 'pending_review'");
    }
};
