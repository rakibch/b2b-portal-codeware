<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, HasTranslations;

    protected array $translatable = ['name', 'short_name'];

    protected $fillable = ['code', 'name', 'short_name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
