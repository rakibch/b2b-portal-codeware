<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceTier extends Model
{
    protected $fillable = ['customer_group_id', 'min_quantity', 'max_quantity', 'unit_price'];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function appliesTo(int $quantity): bool
    {
        return $quantity >= $this->min_quantity
            && ($this->max_quantity === null || $quantity <= $this->max_quantity);
    }

    /** Tiers matching a quantity, ordered so the most specific (highest min) comes first. */
    public function scopeForQuantity(Builder $query, int $quantity): Builder
    {
        return $query->where('min_quantity', '<=', $quantity)
            ->where(fn (Builder $q) => $q->whereNull('max_quantity')->orWhere('max_quantity', '>=', $quantity))
            ->orderByDesc('min_quantity');
    }
}
