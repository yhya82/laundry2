<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mirrors trg_subscriptions_customer_type_guard (which blocks creating a
     * subscription for a non-subscription-type customer) in the other
     * direction -- nothing previously stopped flipping an existing
     * subscriber's customer_type away from 'subscription' while they still
     * had a live subscription, leaving it running under a customer now
     * labelled 'walk_in' and silently dropped from the subscription-eligible
     * customer list.
     */
    public function up(): void
    {
        DB::unprepared('
            CREATE TRIGGER trg_customers_type_change_guard
            BEFORE UPDATE ON customers
            FOR EACH ROW
            BEGIN
                DECLARE live_subscription_count INT;
                IF OLD.customer_type = \'subscription\' AND NEW.customer_type <> \'subscription\' THEN
                    SELECT COUNT(*) INTO live_subscription_count FROM subscriptions
                        WHERE customer_id = NEW.id AND status IN (\'active\', \'paused\');
                    IF live_subscription_count > 0 THEN
                        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Cancel the existing subscription before changing the customer type.\';
                    END IF;
                END IF;
            END
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_customers_type_change_guard');
    }
};
