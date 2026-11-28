<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class CartAndQuickOrderTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        $this->actingAsUser($this->buyer());
    }

    private function id(string $sku): int
    {
        return Product::query()->where('sku', $sku)->value('id');
    }

    public function test_add_to_cart_prices_lines_and_recalculates_on_quantity_change(): void
    {
        $soy = $this->id('OIL-SOY-5L');

        $this->postJson('/api/v1/buyer/cart/items', ['items' => [['product_id' => $soy, 'quantity' => 8]]])
            ->assertOk()
            ->assertJsonPath('data.items.0.pricing.unit_price', '845.00')
            ->assertJsonPath('data.totals.subtotal', '6760.00')
            ->assertJsonPath('data.has_errors', false);

        // Merge: 8 + 32 = 40 → next tier.
        $response = $this->postJson('/api/v1/buyer/cart/items', ['items' => [['product_id' => $soy, 'quantity' => 32]]])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 40)
            ->assertJsonPath('data.items.0.pricing.unit_price', '830.00');

        $itemId = $response->json('data.items.0.id');
        $this->patchJson("/api/v1/buyer/cart/items/{$itemId}", ['quantity' => 100])
            ->assertOk()
            ->assertJsonPath('data.items.0.pricing.unit_price', '815.00');
    }

    public function test_invalid_quantities_are_rejected_atomically(): void
    {
        $this->postJson('/api/v1/buyer/cart/items', ['items' => [
            ['product_id' => $this->id('OIL-SOY-5L'), 'quantity' => 8],
            ['product_id' => $this->id('RIC-MIN-25'), 'quantity' => 60], // MOQ 50 step 25 → invalid
        ]])->assertStatus(422)
            ->assertJsonPath('code', 'cart_lines_invalid')
            ->assertJsonPath('context.lines.0.errors.0.code', 'invalid_step')
            ->assertJsonPath('context.lines.0.errors.0.meta.suggested_quantity', 75);

        $this->getJson('/api/v1/buyer/cart')->assertJsonPath('data.item_count', 0);
    }

    public function test_stock_is_enforced(): void
    {
        // DRY-MLK-1KG is seeded with zero stock.
        $this->postJson('/api/v1/buyer/cart/items', ['items' => [['product_id' => $this->id('DRY-MLK-1KG'), 'quantity' => 12]]])
            ->assertStatus(422)
            ->assertJsonPath('context.lines.0.errors.0.code', 'insufficient_stock');
    }

    public function test_quick_order_validate_reports_matched_and_unmatched_rows(): void
    {
        $this->postJson('/api/v1/buyer/quick-order/validate', ['rows' => [
            ['row' => 1, 'sku' => 'ric-min-25', 'quantity' => 100],
            ['row' => 2, 'sku' => 'NOPE-123', 'quantity' => 5],
            ['row' => 3, 'sku' => 'OIL-SOY-5L', 'quantity' => 'abc'],
            ['row' => 4, 'sku' => 'RIC-MIN-25', 'quantity' => 25], // duplicate → aggregated to 125
        ]])->assertOk()
            ->assertJsonPath('data.summary.matched', 1)
            ->assertJsonPath('data.summary.unmatched', 2)
            ->assertJsonPath('data.matched.0.quantity', 125)
            ->assertJsonPath('data.matched.0.rows', [1, 4])
            ->assertJsonPath('data.unmatched.0.reason', 'not_found')
            ->assertJsonPath('data.unmatched.1.reason', 'invalid_quantity');
    }

    public function test_quick_order_add_to_cart_in_one_click(): void
    {
        $this->postJson('/api/v1/buyer/quick-order/add-to-cart', ['rows' => [
            ['sku' => 'RIC-MIN-25', 'quantity' => 250],
            ['sku' => 'TEA-PRM-400', 'quantity' => 96],
        ]])->assertOk()->assertJsonPath('data.item_count', 2);

        $this->postJson('/api/v1/buyer/quick-order/add-to-cart', ['rows' => [['sku' => 'UNKNOWN', 'quantity' => 1]]])
            ->assertStatus(422)
            ->assertJsonPath('code', 'quick_order_unmatched');
    }

    public function test_csv_import_preview(): void
    {
        $csv = "sku,quantity\nRIC-MIN-25,100\nOIL-SOY-5L,40\nMISSING-1,10\n";
        $file = UploadedFile::fake()->createWithContent('order.csv', $csv);

        $this->post('/api/v1/buyer/quick-order/import', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.summary.matched', 2)
            ->assertJsonPath('data.summary.valid', 2)
            ->assertJsonPath('data.unmatched.0.row', 4)
            ->assertJsonPath('data.unmatched.0.sku', 'MISSING-1');
    }

    public function test_csv_import_rejects_wrong_headers(): void
    {
        $file = UploadedFile::fake()->createWithContent('order.csv', "foo,bar\nA,1\n");

        $this->post('/api/v1/buyer/quick-order/import', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('code', 'import_invalid_headers');
    }

    public function test_price_quote_endpoint(): void
    {
        $this->postJson('/api/v1/catalog/price-quote', ['lines' => [
            ['product_id' => $this->id('OIL-SOY-5L'), 'quantity' => 100],
            ['product_id' => 999999, 'quantity' => 1],
        ]])->assertOk()
            ->assertJsonPath('data.0.unit_price', '815.00')
            ->assertJsonPath('unavailable_product_ids', [999999]);
    }

    public function test_staff_buyer_role_without_cart_permission_is_blocked(): void
    {
        $this->actingAsUser($this->staff(Role::WarehouseStaff));
        $this->postJson('/api/v1/buyer/cart/items', ['items' => [['product_id' => 1, 'quantity' => 1]]])->assertStatus(403);
    }
}
