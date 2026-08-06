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
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->unsignedInteger('max_clothes_per_cycle')->default(1)->after('collections_per_month');
        });

        DB::statement('UPDATE subscription_packages SET max_clothes_per_cycle = clothes_allowance * 4');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->dropColumn('max_clothes_per_cycle');
        });
    }
};
