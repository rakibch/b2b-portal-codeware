<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 16); // bank_transfer | cheque | cash | gateway
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('BDT');
            $table->string('status', 16)->default('pending'); // pending | verified | rejected | failed | refunded

            // Bank transfer / cheque evidence
            $table->string('transaction_ref', 100)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('cheque_number', 50)->nullable();
            $table->date('deposit_date')->nullable();
            $table->string('slip_disk', 32)->nullable();
            $table->string('slip_path')->nullable();

            // Gateway
            $table->string('gateway', 32)->nullable(); // mock | sslcommerz | bkash | nagad | stripe
            $table->string('gateway_transaction_id', 128)->nullable();
            $table->json('gateway_payload')->nullable();

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_transaction_id']); // webhook idempotency
            $table->index(['company_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
            $table->index('invoice_id');
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 32)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->string('status', 16)->default('issued'); // issued | void
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
