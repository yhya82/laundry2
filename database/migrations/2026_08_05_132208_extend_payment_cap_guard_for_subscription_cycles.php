<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_payments_cap_guard');

        // Sum of completed payments against an order (or, for a
        // subscription's flat cycle price, against the cycle) can never
        // exceed its total.
        DB::unprepared("
            CREATE TRIGGER trg_payments_cap_guard
            BEFORE INSERT ON payments
            FOR EACH ROW
            BEGIN
                DECLARE existing_total DECIMAL(10,2);
                DECLARE order_total DECIMAL(10,2);
                DECLARE cycle_total DECIMAL(10,2);
                IF NEW.order_id IS NOT NULL THEN
                    SELECT COALESCE(SUM(amount), 0) INTO existing_total FROM payments WHERE order_id = NEW.order_id AND status = 'completed';
                    SELECT total_amount INTO order_total FROM orders WHERE id = NEW.order_id;
                    IF (existing_total + NEW.amount) > order_total THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment would exceed order total';
                    END IF;
                ELSEIF NEW.subscription_cycle_id IS NOT NULL THEN
                    SELECT COALESCE(SUM(amount), 0) INTO existing_total FROM payments WHERE subscription_cycle_id = NEW.subscription_cycle_id AND status = 'completed';
                    SELECT monthly_price_snapshot INTO cycle_total FROM subscription_cycles WHERE id = NEW.subscription_cycle_id;
                    IF (existing_total + NEW.amount) > cycle_total THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment would exceed cycle total';
                    END IF;
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_payments_cap_guard');

        DB::unprepared("
            CREATE TRIGGER trg_payments_cap_guard
            BEFORE INSERT ON payments
            FOR EACH ROW
            BEGIN
                DECLARE existing_total DECIMAL(10,2);
                DECLARE order_total DECIMAL(10,2);
                IF NEW.order_id IS NOT NULL THEN
                    SELECT COALESCE(SUM(amount), 0) INTO existing_total FROM payments WHERE order_id = NEW.order_id AND status = 'completed';
                    SELECT total_amount INTO order_total FROM orders WHERE id = NEW.order_id;
                    IF (existing_total + NEW.amount) > order_total THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment would exceed order total';
                    END IF;
                END IF;
            END
        ");
    }
};
