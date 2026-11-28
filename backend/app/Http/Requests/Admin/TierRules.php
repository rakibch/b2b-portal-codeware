<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/** Shared validation for price tier arrays (overlap/MOQ checks live in ProductService). */
final class TierRules
{
    public static function rules(string $field, string $presence = 'present'): array
    {
        return [
            $field => [$presence, 'array', 'max:50'],
            "{$field}.*.customer_group_id" => ['nullable', 'integer', Rule::exists('customer_groups', 'id')],
            "{$field}.*.min_quantity" => ['required', 'integer', 'min:1', 'max:10000000'],
            "{$field}.*.max_quantity" => ['nullable', 'integer', 'gte:'.$field.'.*.min_quantity', 'max:10000000'],
            "{$field}.*.unit_price" => ['required', 'decimal:0,2', 'gt:0', 'max:999999999'],
        ];
    }
}
