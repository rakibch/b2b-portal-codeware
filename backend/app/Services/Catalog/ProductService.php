<?php

namespace App\Services\Catalog;

use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    private const IMAGE_DISK = 'public';

    public function __construct(private readonly InventoryService $inventory) {}

    public function create(array $data, User $actor): Product
    {
        return DB::transaction(function () use ($data, $actor): Product {
            $product = new Product($this->attributes($data));
            $product->slug = $this->uniqueSlug($data['name']['en'], $data['sku']);
            $product->forceFill(['stock_quantity' => (int) ($data['stock_quantity'] ?? 0)]);

            if (($data['image'] ?? null) instanceof UploadedFile) {
                $product->image_path = $this->storeImage($data['image']);
            }

            $product->save();

            if (isset($data['tiers'])) {
                $this->syncTiers($product, $data['tiers']);
            }

            AuditLog::record('product.created', $product, [], ['sku' => $product->sku, 'by' => $actor->id]);
            CatalogCache::flush();

            return $product;
        });
    }

    public function update(Product $product, array $data, User $actor): Product
    {
        $oldImage = $product->image_path;

        $product = DB::transaction(function () use ($product, $data, $actor): Product {
            $old = $product->only(['base_price', 'vat_percent', 'moq', 'step_quantity', 'is_active']);
            $product->fill($this->attributes($data));

            if (($data['image'] ?? null) instanceof UploadedFile) {
                $product->image_path = $this->storeImage($data['image']);
            } elseif (! empty($data['remove_image'])) {
                $product->image_path = null;
            }

            $product->save();

            if (array_key_exists('tiers', $data)) {
                $this->syncTiers($product, $data['tiers'] ?? []);
            }

            AuditLog::record('product.updated', $product, $old, $product->only(array_keys($old)) + ['by' => $actor->id]);

            return $product;
        });

        if ($oldImage !== null && $oldImage !== $product->image_path) {
            Storage::disk(self::IMAGE_DISK)->delete($oldImage);
        }

        CatalogCache::flush();

        return $product;
    }

    public function delete(Product $product, User $actor): void
    {
        $product->delete(); // soft delete keeps order history intact
        AuditLog::record('product.deleted', $product, [], ['by' => $actor->id]);
        CatalogCache::flush();
    }

    /**
     * Replaces all tiers. Ranges are validated per customer group (null = general):
     * min >= product MOQ, max >= min, no overlaps.
     *
     * @param  list<array{customer_group_id?: ?int, min_quantity: int, max_quantity?: ?int, unit_price: string}>  $tiers
     */
    public function syncTiers(Product $product, array $tiers): void
    {
        $this->assertValidTiers($product, $tiers);

        DB::transaction(function () use ($product, $tiers): void {
            $product->priceTiers()->delete();

            foreach ($tiers as $tier) {
                $product->priceTiers()->create([
                    'customer_group_id' => $tier['customer_group_id'] ?? null,
                    'min_quantity' => (int) $tier['min_quantity'],
                    'max_quantity' => isset($tier['max_quantity']) ? (int) $tier['max_quantity'] : null,
                    'unit_price' => $tier['unit_price'],
                ]);
            }
        });

        $product->unsetRelation('priceTiers');
    }

    public function adjustStock(Product $product, int $delta, string $reason, User $actor): Product
    {
        return $this->inventory->adjust($product, $delta, $reason, $actor);
    }

    private function assertValidTiers(Product $product, array $tiers): void
    {
        $byGroup = collect($tiers)->groupBy(fn ($t) => $t['customer_group_id'] ?? 'general');

        foreach ($byGroup as $group => $groupTiers) {
            $sorted = $groupTiers->sortBy('min_quantity')->values();

            foreach ($sorted as $i => $tier) {
                $min = (int) $tier['min_quantity'];
                $max = isset($tier['max_quantity']) ? (int) $tier['max_quantity'] : null;

                if ($min < $product->moq) {
                    throw BusinessRuleException::make('tier_below_moq', ['min' => $min, 'moq' => $product->moq]);
                }

                if ($max !== null && $max < $min) {
                    throw BusinessRuleException::make('tier_range_invalid', ['min' => $min, 'max' => $max]);
                }

                $next = $sorted->get($i + 1);
                if ($next !== null && ($max === null || $max >= (int) $next['min_quantity'])) {
                    throw BusinessRuleException::make('tier_overlap', ['min' => (int) $next['min_quantity']]);
                }
            }
        }
    }

    private function attributes(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'sku', 'barcode', 'category_id', 'brand_id', 'unit_id', 'name', 'description', 'pack_size',
            'pack_label', 'base_price', 'vat_percent', 'moq', 'step_quantity', 'max_order_quantity',
            'low_stock_threshold', 'is_active', 'is_featured',
        ]));
    }

    private function uniqueSlug(string $name, string $sku): string
    {
        $base = Str::slug($name.'-'.$sku) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function storeImage(UploadedFile $file): string
    {
        return $file->storeAs('products', Str::uuid()->toString().'.'.($file->guessExtension() ?: 'jpg'), self::IMAGE_DISK);
    }
}
