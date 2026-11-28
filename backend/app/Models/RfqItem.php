<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqItem extends Model
{
    protected $fillable = ['product_id', 'quantity', 'target_unit_price', 'notes'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'target_unit_price' => 'decimal:2',
            'quoted_unit_price' => 'decimal:2',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
