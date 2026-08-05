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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Package fields (collections_per_month, clothes_allowance) are
            // only ever the *default* shown on the create form now -- each
            // subscription stores its own chosen values here so staff can
            // override per customer without touching the shared package.
            $table->unsignedInteger('collections_per_month')->default(1)->after('subscription_package_id');
            $table->enum('collection_type', ['scheduled', 'non_scheduled'])->default('scheduled')->after('collections_per_month');
            $table->unsignedInteger('max_clothes_per_cycle')->default(1)->after('collection_type');
        });

        // Backfill existing subscriptions from their package's current
        // settings, using the same clothes_allowance * 4 formula the create
        // form defaults to, so nothing regresses for subscriptions that
        // already existed before this feature.
        DB::statement('
            UPDATE subscriptions s
            JOIN subscription_packages p ON p.id = s.subscription_package_id
            SET s.collections_per_month = p.collections_per_month,
                s.max_clothes_per_cycle = p.clothes_allowance * 4
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['collections_per_month', 'collection_type', 'max_clothes_per_cycle']);
        });
    }
};
