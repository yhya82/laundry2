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
        //Schema::table('payments', function (Blueprint $table) {
       //     $table->foreignId('subscription_cycle_id')->nullable()->after('subscription_id')
       //         ->constrained('subscription_cycles')->nullOnDelete();
       // });
       if (! Schema::hasColumn('payments', 'subscription_cycle_id')) {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('subscription_cycle_id')
            ->nullable()
            ->after('subscription_id')
            ->constrained('subscription_cycles')
            ->nullOnDelete();
   });
}

        if (DB::getDriverName() === 'mysql') {
    $constraintExists = DB::selectOne("
        SELECT COUNT(*) AS count
        FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'payments'
          AND constraint_name = 'chk_payments_exactly_one_target'
    ");

    if ($constraintExists->count > 0) {
        DB::statement(
            'ALTER TABLE payments DROP CONSTRAINT chk_payments_exactly_one_target'
        );
    }

    DB::statement("
        ALTER TABLE payments
        ADD CONSTRAINT chk_payments_exactly_one_target CHECK (
            (order_id IS NOT NULL AND subscription_id IS NULL)
            OR
            (order_id IS NULL AND subscription_id IS NOT NULL)
        )
    ");
}
        // Backfill: any cycle still using the old anchor-order mechanism gets
        // its price payments moved onto the cycle directly, and the anchor
        // order's subtotal zeroed out (subscription orders never carry the
        // cycle price going forward -- only the cycle itself does). Only
        // handles the unambiguous case, an anchor order with no
        // extra_charge, so the whole thing was purely the cycle's price;
        // anything mixed with an extra charge is left alone for a manual
        // look, since splitting a single payment between the two isn't safe
        // to infer automatically.
        $cycles = DB::table('subscription_cycles')->whereNotNull('anchor_order_id')->get();

        foreach ($cycles as $cycle) {
            $order = DB::table('orders')->find($cycle->anchor_order_id);

            if (! $order || (float) $order->extra_charge > 0) {
                continue;
            }

            DB::table('payments')
                ->where('order_id', $cycle->anchor_order_id)
                ->update([
                    'order_id' => null,
                    'subscription_id' => $cycle->subscription_id,
                    'subscription_cycle_id' => $cycle->id,
                ]);

            DB::table('credit_transactions')
                ->where('reference_type', 'order')
                ->where('reference_id', $cycle->anchor_order_id)
                ->update(['reference_type' => 'subscription_cycle', 'reference_id' => $cycle->id]);

            DB::table('orders')->where('id', $cycle->anchor_order_id)->update(['subtotal' => 0]);
        }

        Schema::table('subscription_cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anchor_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_cycles', function (Blueprint $table) {
            $table->foreignId('anchor_order_id')->nullable()->constrained('orders')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT chk_payments_exactly_one_target');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_exactly_one_target CHECK (
                (order_id IS NOT NULL AND subscription_id IS NULL)
                OR (order_id IS NULL AND subscription_id IS NOT NULL)
            )");
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_cycle_id');
        });
    }
};
