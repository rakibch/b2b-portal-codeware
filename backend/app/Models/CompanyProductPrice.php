<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyProductPrice extends Model
{
    protected $fillable = ['company_id', 'product_id', 'unit_price', 'min_quantity', 'valid_from', 'valid_until', 'created_by'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'min_quantity' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));
    }
}
