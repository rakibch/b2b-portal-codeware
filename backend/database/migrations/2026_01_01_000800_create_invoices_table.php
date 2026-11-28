<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 32)->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('vat_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('amount_credited', 14, 2)->default(0);
            $table->string('status', 16)->default('unpaid'); // unpaid | partially_paid | paid | void
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'due_date']);
            $table->index(['status', 'due_date']); // overdue scans
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
