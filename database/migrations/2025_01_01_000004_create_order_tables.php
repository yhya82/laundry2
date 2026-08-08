<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('order_source', ['walk_in', 'subscription']);
            $table->enum('status', ['received', 'sorting', 'washing', 'drying', 'ironing', 'packaging', 'completed', 'cancelled'])->default('received');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('extra_charge', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->storedAs('subtotal - discount + extra_charge');
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_orders_source_collection CHECK (
                (order_source = 'subscription' AND collection_id IS NOT NULL)
                OR (order_source = 'walk_in' AND collection_id IS NULL)
            )");
        }

        Schema::create('order_package_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('laundry_package_id')->constrained()->restrictOnDelete();
            $table->string('package_name_snapshot');
            $table->decimal('package_price_snapshot', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('order_clothes_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_package_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clothing_item_id')->constrained()->restrictOnDelete();
            $table->string('item_name_snapshot');
            $table->decimal('item_price_snapshot', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_extra')->default(false);
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->unsignedInteger('reprint_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_clothes_lines');
        Schema::dropIfExists('order_package_lines');
        Schema::dropIfExists('orders');
    }
};
