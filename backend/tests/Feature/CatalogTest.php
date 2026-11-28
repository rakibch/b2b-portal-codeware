<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        $this->actingAsUser($this->buyer());
    }

    public function test_product_listing_includes_tier_table_and_localised_names(): void
    {
        $this->withHeader('Accept-Language', 'bn')
            ->getJson('/api/v1/catalog/products?q=OIL-SOY-5L')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'ফর্টিফাইড সয়াবিন তেল ৫ লিটার')
            ->assertJsonPath('data.0.pricing.tier_table.0.unit_price', '845.00')
            ->assertJsonPath('data.0.pricing.quote.quantity', 8)
            ->assertJsonMissingPath('data.0.translations');
    }

    public function test_search_in_bangla_and_english(): void
    {
        $this->getJson('/api/v1/catalog/products?q='.urlencode('সয়াবিন'))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/catalog/products?q=rice')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_category_brand_and_stock_filters(): void
    {
        $oil = Category::query()->where('slug', 'edible-oil')->value('id');

        $this->getJson("/api/v1/catalog/products?category_id={$oil}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/catalog/products?in_stock=1&per_page=100')->assertOk()->assertJsonCount(15, 'data');
    }

    public function test_product_detail_and_lookup(): void
    {
        $slug = $this->getJson('/api/v1/catalog/products?q=TEA-PRM-400')->json('data.0.slug');

        $this->getJson("/api/v1/catalog/products/{$slug}")->assertOk()->assertJsonPath('data.sku', 'TEA-PRM-400');
        $this->getJson('/api/v1/catalog/products/lookup?q=tea')->assertOk()->assertJsonPath('data.0.sku', 'TEA-PRM-400');
    }

    public function test_categories_and_brands_are_cached_lists(): void
    {
        $this->getJson('/api/v1/catalog/categories')->assertOk()->assertJsonCount(8, 'data');
        $this->getJson('/api/v1/catalog/brands')->assertOk()->assertJsonCount(6, 'data');
    }

    public function test_sorting_param_is_whitelisted(): void
    {
        $this->getJson('/api/v1/catalog/products?sort=base_price;drop')->assertStatus(422);
    }
}
