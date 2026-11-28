<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'carrier',
        'tracking_number',
        'vehicle_number',
        'driver_name',
        'driver_phone',
        'received_by_name',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'challan_number';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
}
