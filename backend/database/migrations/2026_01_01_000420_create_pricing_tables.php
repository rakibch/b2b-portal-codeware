<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Volume tiers. customer_group_id NULL = tier applies to every approved buyer.
        Schema::create('product_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_quantity');
            $table->unsignedInteger('max_quantity')->nullable(); // NULL = open ended ("100+")
            $table->decimal('unit_price', 14, 2);
            $table->timestamps();

            $table->unique(['product_id', 'customer_group_id', 'min_quantity'], 'tiers_product_group_min_unique');
            $table->index(['product_id', 'customer_group_id', 'min_quantity'], 'tiers_lookup_index');
        });

        // Contract / customer-specific SKU price overrides (highest precedence).
        Schema::create('company_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 14, 2);
            $table->unsignedInteger('min_quantity')->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'product_id']);
            $table->index(['product_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_product_prices');
        Schema::dropIfExists('product_price_tiers');
    }
};
