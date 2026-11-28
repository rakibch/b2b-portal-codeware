<?php

namespace App\Models;

use App\Enums\AddressType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyAddress extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'label',
        'contact_name',
        'contact_phone',
        'address_line1',
        'address_line2',
        'area',
        'city',
        'district',
        'postal_code',
        'country_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
            'is_default' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeShipping(Builder $query): Builder
    {
        return $query->where('type', AddressType::Shipping->value);
    }

    public function scopeBilling(Builder $query): Builder
    {
        return $query->where('type', AddressType::Billing->value);
    }

    /** Immutable snapshot stored on orders/invoices. */
    public function toSnapshot(): array
    {
        return [
            'label' => $this->label,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'area' => $this->area,
            'city' => $this->city,
            'district' => $this->district,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
        ];
    }

    public function fullAddress(): string
    {
        return collect([
            $this->address_line1,
            $this->address_line2,
            $this->area,
            $this->city,
            $this->district,
            $this->postal_code,
        ])->filter()->implode(', ');
    }
}
