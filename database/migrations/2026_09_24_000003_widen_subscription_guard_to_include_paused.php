<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Widens trg_subscriptions_one_active_guard_insert/update from blocking
     * only against another 'active' subscription to blocking against
     * 'active' OR 'paused' -- a paused subscription still represents an
     * open relationship staff haven't closed out, so starting a new one
     * now requires cancelling the old one first, not just letting it sit
     * paused indefinitely alongside a fresh one.
     */
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_subscriptions_one_active_guard_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_subscriptions_one_active_guard_update');

        DB::unprepared('
            CREATE TRIGGER trg_subscriptions_one_active_guard_insert
            BEFORE INSERT ON subscriptions
            FOR EACH ROW
            BEGIN
                DECLARE blocking_count INT;
                IF NEW.status = \'active\' THEN
                    SELECT COUNT(*) INTO blocking_count FROM subscriptions
                        WHERE customer_id = NEW.customer_id AND status IN (\'active\', \'paused\');
                    IF blocking_count > 0 THEN
                        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Cancel the existing subscription before creating a new one.\';
                    END IF;
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_subscriptions_one_active_guard_update
            BEFORE UPDATE ON subscriptions
            FOR EACH ROW
            BEGIN
                DECLARE blocking_count INT;
                IF NEW.status = \'active\' THEN
                    SELECT COUNT(*) INTO blocking_count FROM subscriptions
                        WHERE customer_id = NEW.customer_id AND status IN (\'active\', \'paused\') AND id <> NEW.id;
                    IF blocking_count > 0 THEN
                        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Cancel the existing subscription before creating a new one.\';
                    END IF;
                END IF;
            END
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_subscriptions_one_active_guard_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_subscriptions_one_active_guard_update');

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
};
