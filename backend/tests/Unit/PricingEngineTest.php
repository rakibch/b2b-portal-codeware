<?php

namespace Tests\Unit;

use App\Enums\PriceSource;
use App\Models\CompanyProductPrice;
use App\Models\Product;
use App\Services\Pricing\PricingEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    private PricingEngineService $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        $this->engine = app(PricingEngineService::class);
    }

    private function soybean(): Product
    {
        // base 860, tiers: 8–39 = 845, 40–99 = 830, 100+ = 815; gold 40+ = 822; distributor 100+ = 805; MOQ 8, step 4, VAT 5%
        return Product::query()->where('sku', 'OIL-SOY-5L')->firstOrFail();
    }

    public function test_general_tiers_apply_by_quantity(): void
    {
        $company = $this->approvedCompany();

        $this->assertSame('845.00', $this->engine->quote($this->soybean(), 8, $company)->unitPrice);
        $this->assertSame('830.00', $this->engine->quote($this->soybean(), 40, $company)->unitPrice);
        $this->assertSame('815.00', $this->engine->quote($this->soybean(), 100, $company)->unitPrice);
        $this->assertSame(PriceSource::Tier, $this->engine->quote($this->soybean(), 40, $company)->source);
    }

    public function test_group_discount_and_group_tier_lowest_wins(): void
    {
        $gold = $this->approvedCompany('gold'); // 2% off general prices; 40+ group tier 822

        // 8 units: general tier 845 less 2% = 828.10
        $this->assertSame('828.10', $this->engine->quote($this->soybean(), 8, $gold)->unitPrice);
        // 40 units: general 830 less 2% = 813.40 beats group tier 822
        $quote = $this->engine->quote($this->soybean(), 40, $gold);
        $this->assertSame('813.40', $quote->unitPrice);
        $this->assertSame(PriceSource::Tier, $quote->source);

        $distributor = $this->approvedCompany('distributor'); // 5% off; 100+ group tier 805
        // 100 units: 815 less 5% = 774.25 beats 805
        $this->assertSame('774.25', $this->engine->quote($this->soybean(), 100, $distributor)->unitPrice);
    }

    public function test_contract_price_applies_when_lowest_and_above_min_quantity(): void
    {
        $company = $this->approvedCompany();
        CompanyProductPrice::query()->create([
            'company_id' => $company->id,
            'product_id' => $this->soybean()->id,
            'unit_price' => '800.00',
            'min_quantity' => 20,
        ]);

        $this->assertSame('845.00', $this->engine->quote($this->soybean(), 12, $company)->unitPrice);
        $quote = $this->engine->quote($this->soybean(), 20, $company);
        $this->assertSame('800.00', $quote->unitPrice);
        $this->assertSame(PriceSource::Contract, $quote->source);
    }

    public function test_expired_contract_price_is_ignored(): void
    {
        $company = $this->approvedCompany();
        CompanyProductPrice::query()->create([
            'company_id' => $company->id,
            'product_id' => $this->soybean()->id,
            'unit_price' => '500.00',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame('845.00', $this->engine->quote($this->soybean(), 8, $company)->unitPrice);
    }

    public function test_line_totals_and_vat_are_exact(): void
    {
        $quote = $this->engine->quote($this->soybean(), 40, $this->approvedCompany());

        $this->assertSame('33200.00', $quote->lineSubtotal);  // 830 × 40
        $this->assertSame('1200.00', $quote->discountAmount); // (860 − 830) × 40
        $this->assertSame('1660.00', $quote->vatAmount);      // 5%
        $this->assertSame('34860.00', $quote->lineTotal);
    }

    public function test_moq_step_max_and_stock_rules(): void
    {
        $product = $this->soybean();
        $product->forceFill(['max_order_quantity' => 200, 'stock_quantity' => 100])->save();

        $this->assertTrue($this->engine->quote($product, 4, null)->hasError('below_moq'));
        $step = $this->engine->quote($product, 10, null);
        $this->assertTrue($step->hasError('invalid_step'));
        $this->assertSame(12, $step->errors[0]['meta']['suggested_quantity']);
        $this->assertTrue($this->engine->quote($product, 204, null)->hasError('above_max'));
        $this->assertTrue($this->engine->quote($product, 104, null)->hasError('insufficient_stock'));
        $this->assertFalse($this->engine->quote($product, 104, null, checkStock: false)->hasError('insufficient_stock'));
        $this->assertTrue($this->engine->quote($product, 100, null)->isValid());
    }

    public function test_next_tier_hint_and_tier_table(): void
    {
        $company = $this->approvedCompany();
        $quote = $this->engine->quote($this->soybean(), 32, $company);

        $this->assertSame(['min_quantity' => 40, 'unit_price' => '830.00', 'additional_quantity' => 8], $quote->nextTier);

        $table = $this->engine->tierTable($this->soybean(), $company);
        $this->assertSame([8, 40, 100], array_column($table, 'min_quantity'));
        $this->assertSame([39, 99, null], array_column($table, 'max_quantity'));
        $this->assertSame(['845.00', '830.00', '815.00'], array_column($table, 'unit_price'));
    }

    public function test_quote_many_runs_constant_queries(): void
    {
        $company = $this->approvedCompany('gold');
        $products = Product::query()->get();

        \DB::enableQueryLog();
        $this->engine->quoteMany($products->map(fn ($p) => [$p, $p->moq]), $company);
        $queries = count(\DB::getQueryLog());

        $this->assertLessThanOrEqual(3, $queries, 'Pricing must not issue per-line queries.');
    }
}
