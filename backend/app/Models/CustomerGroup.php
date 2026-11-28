<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends Model
{
    use HasTranslations;

    protected array $translatable = ['name', 'description'];

    protected $fillable = ['code', 'name', 'description', 'discount_percent', 'priority', 'is_active'];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
