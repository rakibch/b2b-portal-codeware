<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'sku' => 'SKU-'.strtoupper(Str::random(8)),
            'barcode' => fake()->unique()->ean13(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'name' => ['en' => $name, 'bn' => $name],
            'description' => ['en' => fake()->sentence()],
            'pack_size' => 12,
            'pack_label' => ['en' => 'Carton', 'bn' => 'কার্টন'],
            'base_price' => '100.00',
            'vat_percent' => '5.00',
            'moq' => 10,
            'step_quantity' => 5,
            'stock_quantity' => 1000,
            'low_stock_threshold' => 50,
            'is_active' => true,
        ];
    }
}
