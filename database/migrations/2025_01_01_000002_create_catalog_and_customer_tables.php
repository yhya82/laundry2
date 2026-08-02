<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->enum('customer_type', ['walk_in', 'subscription'])->default('walk_in');
            $table->decimal('store_credit_balance', 10, 2)->default(0);
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('full_name');
            $table->index('customer_type');
        });

        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_phone_format CHECK (phone REGEXP '^[+0-9][0-9 ()-]{6,19}$')");

        Schema::create('clothes_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('clothing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clothes_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('image_path')->nullable();
            $table->string('image_mime')->nullable();
            $table->unsignedInteger('image_size')->nullable();
            $table->timestamps();
        });

        Schema::create('laundry_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('monthly_price', 10, 2);
            $table->unsignedInteger('clothes_allowance');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
        Schema::dropIfExists('laundry_packages');
        Schema::dropIfExists('clothing_items');
        Schema::dropIfExists('clothes_categories');
        Schema::dropIfExists('customers');
    }
};
