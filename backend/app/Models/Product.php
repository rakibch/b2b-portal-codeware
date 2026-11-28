<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'description', 'pack_label'];

    /** stock_quantity is intentionally excluded: stock moves only via InventoryService under lock. */
    protected $fillable = [
        'sku',
        'barcode',
        'slug',
        'category_id',
        'brand_id',
        'unit_id',
        'name',
        'description',
        'pack_size',
        'pack_label',
        'base_price',
        'vat_percent',
        'moq',
        'step_quantity',
        'max_order_quantity',
        'low_stock_threshold',
        'image_path',
        'is_active',
        'is_featured',
    ];

    protected $hidden = ['search_index'];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'vat_percent' => 'decimal:2',
            'pack_size' => 'integer',
            'moq' => 'integer',
            'step_quantity' => 'integer',
            'max_order_quantity' => 'integer',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            $product->search_index = $product->buildSearchIndex();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('min_quantity');
    }

    /** Tiers available to everyone (no customer group). */
    public function generalPriceTiers(): HasMany
    {
        return $this->priceTiers()->whereNull('customer_group_id');
    }

    public function companyPrices(): HasMany
    {
        return $this->hasMany(CompanyProductPrice::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function isInStock(int $quantity = 1): bool
    {
        return $this->stock_quantity >= $quantity;
    }

    /** Stock badge key: in_stock | low_stock | out_of_stock. */
    public function stockStatus(): string
    {
        return match (true) {
            $this->stock_quantity <= 0 => 'out_of_stock',
            $this->isLowStock() => 'low_stock',
            default => 'in_stock',
        };
    }

    /** Quantity satisfies MOQ, step increments and max order quantity. */
    public function isValidOrderQuantity(int $quantity): bool
    {
        if ($quantity < max(1, $this->moq)) {
            return false;
        }

        if ($this->max_order_quantity !== null && $quantity > $this->max_order_quantity) {
            return false;
        }

        $step = max(1, $this->step_quantity);

        return ($quantity - $this->moq) % $step === 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    /** Fast prefix/contains search over the denormalised index; FULLTEXT on MySQL/PG for 3+ char terms. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $needle = mb_strtolower($term);
        $driver = DB::connection($this->getConnectionName())->getDriverName();

        return $query->where(function (Builder $q) use ($needle, $driver, $term): void {
            $q->where('sku', $term)
                ->orWhere('barcode', $term);

            if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true) && mb_strlen($needle) >= 3) {
                $q->orWhereFullText('search_index', $needle);
            }

            $q->orWhere('search_index', 'like', '%'.addcslashes($needle, '%_\\').'%');
        });
    }

    public function buildSearchIndex(): string
    {
        $parts = [$this->sku, $this->barcode];

        foreach (['name'] as $attribute) {
            foreach ($this->getTranslations($attribute) as $value) {
                $parts[] = $value;
            }
        }

        return mb_substr(mb_strtolower(implode(' ', array_filter($parts))), 0, 1000);
    }
}
