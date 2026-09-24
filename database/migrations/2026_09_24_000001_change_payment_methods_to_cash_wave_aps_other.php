<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Only populated (and required) when method = 'other' -- lets
            // staff record what an out-of-band payment actually was instead
            // of it just being an unexplained "Other" in the history.
            $table->string('method_note')->nullable()->after('method');
        });

        // 'card' and 'mixed' have no direct equivalent in the new set --
        // reclassified to 'other' with the original value preserved in
        // method_note so the history isn't silently lost.
        DB::statement("
            UPDATE payments
            SET method_note = CONCAT(\"Migrated from '\", method, \"'\")
            WHERE method IN ('card', 'mixed')
        ");

        // Widen first so both the old and new values are valid while rows
        // are reassigned, then narrow to the final set once nothing depends
        // on 'card'/'mixed' anymore.
        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash', 'card', 'store_credit', 'mixed', 'wave', 'aps', 'other') NOT NULL");
        DB::statement("UPDATE payments SET method = 'other' WHERE method IN ('card', 'mixed')");
        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash', 'wave', 'aps', 'other', 'store_credit') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash', 'card', 'store_credit', 'mixed') NOT NULL");

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('method_note');
        });
    }
};
