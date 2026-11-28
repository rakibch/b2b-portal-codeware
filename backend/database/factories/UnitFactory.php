<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'code' => 'u'.Str::lower(Str::random(6)),
            'name' => ['en' => 'Piece', 'bn' => 'পিস'],
            'short_name' => ['en' => 'pcs', 'bn' => 'পিস'],
            'is_active' => true,
        ];
    }
}
