<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Collapses the 7-stage processing pipeline (received -> sorting ->
     * washing -> drying -> ironing -> packaging -> completed -> collection)
     * down to 4 stages (received -> wash -> completed -> collection) -- too
     * granular from a business perspective; sorting/washing/drying/ironing/
     * packaging all become one 'wash' stage. Machine assignment/busy
     * tracking deliberately keeps using a single literal status value for
     * "currently being washed" -- it's just 'wash' now instead of
     * 'washing' -- see WashingMachine::currentOrder().
     *
     * 'collection' is left exactly as it is -- it already displays as
     * "Collected" everywhere (status-pill.blade.php's label override), so
     * there's nothing to rename, only to reposition as the stage after
     * 'wash' instead of after 'completed' -> 'collection' via five more
     * stages in between.
     */
    public function up(): void
    {
        // Widen first so both old and new values are valid while rows are
        // reclassified (matches the pattern used for the 'closed' removal
        // and payment-method migrations earlier this project).
        DB::statement("ALTER TABLE orders MODIFY status ENUM('received', 'sorting', 'washing', 'drying', 'ironing', 'packaging', 'completed', 'collection', 'cancelled', 'wash') NOT NULL DEFAULT 'received'");

        DB::statement("UPDATE orders SET status = 'wash' WHERE status IN ('sorting', 'washing', 'drying', 'ironing', 'packaging')");

        // order_status_history.to_status/from_status are plain strings, not
        // enum-constrained, so historical rows recording e.g. 'sorting' or
        // 'drying' stay exactly as they were written -- an accurate record
        // of what the pipeline looked like at the time, not reclassified.

        DB::statement("ALTER TABLE orders MODIFY status ENUM('received', 'wash', 'completed', 'collection', 'cancelled') NOT NULL DEFAULT 'received'");

        DB::unprepared('DROP TRIGGER IF EXISTS trg_orders_status_transition_guard');

        DB::unprepared("
            CREATE TRIGGER trg_orders_status_transition_guard
            BEFORE UPDATE ON orders
            FOR EACH ROW
            BEGIN
                IF NEW.status <> OLD.status THEN
                    IF OLD.status IN ('collection', 'cancelled') THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order status is terminal and cannot change';
                    ELSEIF NEW.status = 'cancelled' THEN
                        IF OLD.status = 'completed' THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid order status transition';
                        END IF;
                    ELSEIF NOT (
                        (OLD.status = 'received' AND NEW.status = 'wash') OR
                        (OLD.status = 'wash' AND NEW.status = 'completed') OR
                        (OLD.status = 'completed' AND NEW.status = 'collection')
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid order status transition';
                    END IF;
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_orders_status_transition_guard');

        DB::statement("ALTER TABLE orders MODIFY status ENUM('received', 'sorting', 'washing', 'drying', 'ironing', 'packaging', 'completed', 'collection', 'cancelled', 'wash') NOT NULL DEFAULT 'received'");

        // One-way: a 'wash' row can't be un-collapsed back into whichever
        // of the 5 original stages it used to be -- that information was
        // lost the moment it was merged. Rolling back just keeps it as
        // 'wash' (now re-widened back into the enum) rather than guessing.

        DB::statement("ALTER TABLE orders MODIFY status ENUM('received', 'sorting', 'washing', 'drying', 'ironing', 'packaging', 'completed', 'collection', 'cancelled') NOT NULL DEFAULT 'received'");

        DB::unprepared("
            CREATE TRIGGER trg_orders_status_transition_guard
            BEFORE UPDATE ON orders
            FOR EACH ROW
            BEGIN
                IF NEW.status <> OLD.status THEN
                    IF OLD.status IN ('collection', 'cancelled') THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order status is terminal and cannot change';
                    ELSEIF NEW.status = 'cancelled' THEN
                        IF OLD.status = 'completed' THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid order status transition';
                        END IF;
                    ELSEIF NOT (
                        (OLD.status = 'received' AND NEW.status = 'sorting') OR
                        (OLD.status = 'sorting' AND NEW.status = 'washing') OR
                        (OLD.status = 'washing' AND NEW.status = 'drying') OR
                        (OLD.status = 'drying' AND NEW.status = 'ironing') OR
                        (OLD.status = 'ironing' AND NEW.status = 'packaging') OR
                        (OLD.status = 'packaging' AND NEW.status = 'completed') OR
                        (OLD.status = 'completed' AND NEW.status = 'collection')
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid order status transition';
                    END IF;
                END IF;
            END
        ");
    }
};
