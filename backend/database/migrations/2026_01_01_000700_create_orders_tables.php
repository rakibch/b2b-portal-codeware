<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('placed_by')->constrained('users')->restrictOnDelete();
            // Set when a Sales/Account Manager creates the order on behalf of the buyer.
            $table->foreignId('created_by_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();

            $table->string('status', 24)->default('pending');
            // Buyer-internal approval for sub-users exceeding their threshold.
            $table->string('approval_status', 16)->default('not_required');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->string('payment_method', 16);
            $table->string('payment_status', 24)->default('unpaid');

            $table->foreignId('shipping_address_id')->nullable()->constrained('company_addresses')->nullOnDelete();
            // Immutable address snapshots (addresses may later change/be deleted).
            $table->json('billing_address');
            $table->json('shipping_address');

            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('vat_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->string('locale', 5)->default('en');

            $table->string('po_number', 64)->nullable(); // buyer's purchase order ref
            $table->text('buyer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['company_id', 'approval_status']);
            $table->index('payment_status');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Snapshots so documents stay correct after catalog edits.
            $table->string('sku', 64);
            $table->json('product_name');
            $table->json('unit_name');
            $table->unsignedInteger('pack_size')->default(1);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('dispatched_quantity')->default(0);
            $table->decimal('list_price', 14, 2);
            $table->decimal('unit_price', 14, 2);
            $table->string('price_source', 16); // base | tier | group | contract | rfq
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index(['product_id', 'order_id']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        Schema::table('rfqs', function (Blueprint $table) {
            $table->foreign('converted_order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropForeign(['converted_order_id']);
        });
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
