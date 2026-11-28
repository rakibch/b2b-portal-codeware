<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('submitted');
            $table->text('buyer_notes')->nullable();
            $table->text('quote_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            // FK to orders added after orders table exists.
            $table->unsignedBigInteger('converted_order_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'valid_until']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('target_unit_price', 14, 2)->nullable();
            $table->decimal('quoted_unit_price', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rfq_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
    }
};
