<?php

namespace App\Models;

use App\Enums\CartItemSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['product_id', 'quantity', 'source'];

    protected $touches = ['cart'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'source' => CartItemSource::class,
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
