<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('monthly_price_snapshot', 10, 2);
            $table->foreignId('anchor_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();

            $table->unique(['subscription_id', 'starts_on']);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('subscription_cycle_id')->nullable()->after('subscription_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_cycle_id');
        });

        Schema::dropIfExists('subscription_cycles');
    }
};
