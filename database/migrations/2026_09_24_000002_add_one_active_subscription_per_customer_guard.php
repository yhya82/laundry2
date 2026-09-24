<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replaces the app-level-only subscription.max_active_packages_per_customer
     * check -- that only ever ran in StoreSubscriptionRequest, so
     * SubscriptionController::resume() could still produce two active
     * subscriptions for the same customer (pause A, create active B, resume
     * A -- nothing blocked it). Both INSERT and UPDATE need the guard since
     * resume() reaches 'active' via an UPDATE, not just creation.
     */
    public function up(): void
    {
        DB::unprepared('
            CREATE TRIGGER trg_subscriptions_one_active_guard_insert
            BEFORE INSERT ON subscriptions
            FOR EACH ROW
            BEGIN
                DECLARE active_count INT;
                IF NEW.status = \'active\' THEN
                    SELECT COUNT(*) INTO active_count FROM subscriptions
                        WHERE customer_id = NEW.customer_id AND status = \'active\';
                    IF active_count > 0 THEN
                        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'This customer already has an active subscription.\';
                    END IF;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_subscriptions_one_active_guard_update
            BEFORE UPDATE ON subscriptions
            FOR EACH ROW
            BEGIN
                DECLARE active_count INT;
                IF NEW.status = \'active\' THEN
                    SELECT COUNT(*) INTO active_count FROM subscriptions
                        WHERE customer_id = NEW.customer_id AND status = \'active\' AND id <> NEW.id;
                    IF active_count > 0 THEN
                        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'This customer already has an active subscription.\';
                    END IF;
                END IF;
            END
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_subscriptions_one_active_guard_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_subscriptions_one_active_guard_update');
    }
};
