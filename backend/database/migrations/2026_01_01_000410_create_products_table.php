<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('slug')->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            // Denormalised lowercase haystack (sku, barcode, en+bn names) for fast live search.
            $table->string('search_index', 1000)->default('');

            // Packaging: 1 pack (e.g. Carton) = pack_size base units.
            $table->unsignedInteger('pack_size')->default(1);
            $table->json('pack_label')->nullable();

            // Pricing (base-unit price, VAT exclusive).
            $table->decimal('base_price', 14, 2);
            $table->decimal('vat_percent', 5, 2)->default(0);

            // Ordering rules (base units).
            $table->unsignedInteger('moq')->default(1);
            $table->unsignedInteger('step_quantity')->default(1);
            $table->unsignedInteger('max_order_quantity')->nullable();

            // Inventory (base units). Mutated only inside locked transactions.
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);

            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'category_id']);
            $table->index(['is_active', 'brand_id']);
            $table->index(['is_active', 'is_featured']);
            $table->index('search_index');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            Schema::table('products', function (Blueprint $table) {
                $table->fullText('search_index');
            });
        }

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->json('alt_text')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
