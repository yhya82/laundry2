<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirrors order_status_history: damage review isn't a linear pipeline
        // (pending_review can skip straight to approved/rejected), so this is
        // a plain log rather than a fixed-stage timeline, but the same
        // question -- "who moved this and when" -- applies just as much here.
        Schema::create('damage_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_record_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Fires on every status change, including the resolve() flow's
        // UPDATE damage_records SET status = 'resolved' (trg_damage_resolutions_apply_status)
        // -- so the resolver's identity lands in the same trail as the
        // review-chain moves, not just in damage_resolutions.resolved_by.
        DB::unprepared("
            CREATE TRIGGER trg_damage_records_status_history_log
            AFTER UPDATE ON damage_records
            FOR EACH ROW
            BEGIN
                IF NEW.status <> OLD.status THEN
                    INSERT INTO damage_status_history (damage_record_id, from_status, to_status, changed_by, created_at)
                    VALUES (NEW.id, OLD.status, NEW.status, @current_user_id, NOW());
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_damage_records_status_history_log');
        Schema::dropIfExists('damage_status_history');
    }
};
