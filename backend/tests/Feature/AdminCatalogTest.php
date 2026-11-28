<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
    }

    private function payload(array $override = []): array
    {
        return array_replace([
            'sku' => 'sug-wht-1kg',
            'category_id' => Category::query()->value('id'),
            'unit_id' => Unit::query()->where('code', 'pkt')->value('id'),
            'name' => ['en' => 'White Sugar 1 kg', 'bn' => 'সাদা চিনি ১ কেজি'],
            'pack_size' => 20,
            'pack_label' => ['en' => 'Bale', 'bn' => 'বেল'],
            'base_price' => '140.00',
            'vat_percent' => '5',
            'moq' => 20,
            'step_quantity' => 20,
            'stock_quantity' => 500,
            'tiers' => [
                ['min_quantity' => 20, 'max_quantity' => 99, 'unit_price' => '136.00'],
                ['min_quantity' => 100, 'unit_price' => '132.00'],
            ],
        ], $override);
    }

    public function test_super_admin_creates_product_with_tiers(): void
    {
        $this->actingAsUser($this->staff());

        $this->postJson('/api/v1/admin/products', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.sku', 'SUG-WHT-1KG')
            ->assertJsonPath('data.translations.name.bn', 'সাদা চিনি ১ কেজি')
            ->assertJsonCount(2, 'data.tiers');

        $this->assertSame(500, Product::query()->where('sku', 'SUG-WHT-1KG')->value('stock_quantity'));
    }

    public function test_overlapping_or_sub_moq_tiers_are_rejected(): void
    {
        $this->actingAsUser($this->staff());

        $this->postJson('/api/v1/admin/products', $this->payload(['tiers' => [
            ['min_quantity' => 20, 'max_quantity' => 120, 'unit_price' => '136.00'],
            ['min_quantity' => 100, 'unit_price' => '132.00'],
        ]]))->assertStatus(422)->assertJsonPath('code', 'tier_overlap');

        $this->postJson('/api/v1/admin/products', $this->payload(['tiers' => [['min_quantity' => 5, 'unit_price' => '136.00']]]))
            ->assertStatus(422)->assertJsonPath('code', 'tier_below_moq');

        $this->assertSame(0, Product::query()->where('sku', 'SUG-WHT-1KG')->count());
    }

    public function test_stock_cannot_be_mass_assigned_on_update_and_is_adjusted_via_endpoint(): void
    {
        $this->actingAsUser($this->staff());
        $product = Product::query()->where('sku', 'TEA-PRM-400')->firstOrFail();

        $this->patchJson("/api/v1/admin/products/{$product->id}", ['stock_quantity' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('stock_quantity');

        $this->actingAsUser($this->staff(Role::WarehouseStaff));
        $this->postJson("/api/v1/admin/products/{$product->id}/stock-adjustments", ['delta' => -400, 'reason' => 'Damaged in transit'])
            ->assertOk()->assertJsonPath('data.available_quantity', 2000);

        $this->postJson("/api/v1/admin/products/{$product->id}/stock-adjustments", ['delta' => -999999, 'reason' => 'x x'])
            ->assertStatus(422)->assertJsonPath('code', 'stock_negative');
    }

    public function test_warehouse_and_sales_cannot_edit_catalog(): void
    {
        foreach ([Role::WarehouseStaff, Role::SalesManager] as $role) {
            $this->actingAsUser($this->staff($role));
            $this->postJson('/api/v1/admin/products', $this->payload())->assertStatus(403);
            $this->getJson('/api/v1/admin/products')->assertOk();
        }
    }

    public function test_category_crud_with_translations(): void
    {
        $this->actingAsUser($this->staff());

        $id = $this->postJson('/api/v1/admin/categories', ['name' => ['en' => 'Frozen Food', 'bn' => 'হিমায়িত খাবার']])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'frozen-food')
            ->json('data.id');

        $this->withHeader('Accept-Language', 'bn')->getJson('/api/v1/catalog/categories')->assertJsonFragment(['name' => 'হিমায়িত খাবার']);

        $this->deleteJson("/api/v1/admin/categories/{$id}")->assertOk();
        $used = Category::query()->where('slug', 'edible-oil')->value('id');
        $this->deleteJson("/api/v1/admin/categories/{$used}")->assertStatus(422)->assertJsonPath('code', 'category_in_use');
    }

    public function test_contract_prices_per_company(): void
    {
        $this->actingAsUser($this->staff());
        $company = $this->approvedCompany();
        $product = Product::query()->where('sku', 'TEA-PRM-400')->firstOrFail();

        $this->postJson("/api/v1/admin/companies/{$company->public_id}/prices", ['product_id' => $product->id, 'unit_price' => '210.00'])
            ->assertCreated()->assertJsonPath('data.unit_price', '210.00');

        $this->postJson("/api/v1/admin/companies/{$company->public_id}/prices", ['product_id' => $product->id, 'unit_price' => '200.00'])
            ->assertStatus(422)->assertJsonValidationErrors('product_id');
    }
}
