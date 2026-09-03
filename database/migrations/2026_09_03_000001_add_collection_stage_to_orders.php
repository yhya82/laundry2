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
        DB::statement("ALTER TABLE orders MODIFY status ENUM('received', 'sorting', 'washing', 'drying', 'ironing', 'packaging', 'completed', 'collection', 'cancelled') NOT NULL DEFAULT 'received'");

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('collected_by_type', ['customer', 'other'])->nullable()->after('washing_machine_id');
            $table->string('collected_by_name')->nullable()->after('collected_by_type');
        });

        DB::unprepared('DROP TRIGGER IF EXISTS trg_orders_status_transition_guard');

        // 'collection' (customer physically picked it up) is now the true
        // terminal stage -- 'completed' just means processing is done, not
        // that the order has left the building. 'completed' deliberately
        // stays non-cancellable even though it's no longer terminal: cancelling
        // a fully-processed order doesn't make operational sense, so it's
        // excluded from the "cancel from any non-terminal stage" rule below,
        // same as it was implicitly excluded before by being terminal.
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_orders_status_transition_guard');

        DB::unprepared("
            CREATE TRIGGER trg_orders_status_transition_guard
            BEFORE UPDATE ON orders
            FOR EACH ROW
            BEGIN
                IF NEW.status <> OLD.status THEN
                    IF OLD.status IN ('completed', 'cancelled') THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order status is terminal and cannot change';
                    ELSEIF NEW.status = 'cancelled' THEN
                        SET @noop = 1;
                    ELSEIF NOT (
                        (OLD.status = 'received' AND NEW.status = 'sorting') OR
                        (OLD.status = 'sorting' AND NEW.status = 'washing') OR
                        (OLD.status = 'washing' AND NEW.status = 'drying') OR
                        (OLD.status = 'drying' AND NEW.status = 'ironing') OR
                        (OLD.status = 'ironing' AND NEW.status = 'packaging') OR
                        (OLD.status = 'packaging' AND NEW.status = 'completed')
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid order status transition';
                    END IF;
                END IF;
            END
        ");

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['collected_by_type', 'collected_by_name']);
        });

        DB::statement("ALTER TABLE orders MODIFY status ENUM('received', 'sorting', 'washing', 'drying', 'ironing', 'packaging', 'completed', 'cancelled') NOT NULL DEFAULT 'received'");
    }
};
