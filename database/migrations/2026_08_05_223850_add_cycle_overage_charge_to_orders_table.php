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
        DB::statement('ALTER TABLE orders ADD COLUMN cycle_overage_charge DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER extra_charge');

        // total_amount is a generated column -- MODIFY replaces the whole
        // definition, so the formula is re-stated in full rather than
        // appended to.
        DB::statement('ALTER TABLE orders MODIFY total_amount DECIMAL(10,2) GENERATED ALWAYS AS (subtotal - discount + extra_charge + cycle_overage_charge) STORED');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE orders MODIFY total_amount DECIMAL(10,2) GENERATED ALWAYS AS (subtotal - discount + extra_charge) STORED');
        DB::statement('ALTER TABLE orders DROP COLUMN cycle_overage_charge');
    }
};
