<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('damage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('damage_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('item_description');
            $table->string('stage_at_report')->nullable();
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('status', ['pending_review', 'under_investigation', 'approved', 'rejected', 'resolved', 'closed'])->default('pending_review');
            $table->timestamps();
        });

        Schema::create('damage_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_record_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('resolution_type', ['cash', 'store_credit', 'replacement']);
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Draft table — modeled on the damage pattern, not yet business-confirmed (Master Document §8.1).
        Schema::create('customer_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->enum('status', ['open', 'in_review', 'resolved', 'closed'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_complaints');
        Schema::dropIfExists('damage_resolutions');
        Schema::dropIfExists('damage_records');
        Schema::dropIfExists('damage_types');
    }
};
