<?php

namespace App\Http\Resources;

use App\Models\Shipment;
use Illuminate\Http\Request;

/** @mixin Shipment */
class ShipmentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'challan_number' => $this->challan_number,
            'order_number' => $this->whenLoaded('order', fn () => $this->order->order_number),
            'status' => $this->enum($this->status),
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'vehicle_number' => $this->vehicle_number,
            'driver_name' => $this->driver_name,
            'driver_phone' => $this->driver_phone,
            'received_by_name' => $this->received_by_name,
            'notes' => $this->notes,
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($l) => [
                'order_item_id' => $l->order_item_id,
                'sku' => $l->relationLoaded('orderItem') ? $l->orderItem->sku : null,
                'name' => $l->relationLoaded('orderItem') ? $l->orderItem->product_name : null,
                'quantity' => $l->quantity,
            ])),
            'challan_path' => '/api/v1/shipments/'.$this->challan_number.'/challan',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
