<?php

namespace App\Models;

use App\Enums\PriceSource;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasTranslations;

    protected array $translatable = ['product_name', 'unit_name'];

    protected $fillable = [
        'product_id',
        'sku',
        'product_name',
        'unit_name',
        'pack_size',
        'quantity',
        'list_price',
        'unit_price',
        'price_source',
        'vat_percent',
        'line_subtotal',
        'discount_amount',
        'vat_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'pack_size' => 'integer',
            'quantity' => 'integer',
            'dispatched_quantity' => 'integer',
            'list_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'price_source' => PriceSource::class,
            'vat_percent' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->dispatched_quantity);
    }
}
