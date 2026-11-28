<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\DocumentSequence;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Warehouse flow: pack → dispatch (one Delivery Challan per shipment, partial allowed) → deliver.
 * Order status follows: confirmed → processing → partially_dispatched / dispatched → delivered.
 */
class FulfilmentService
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * @param  array<int, int>  $quantities  order_item_id => quantity
     */
    public function createShipment(Order $order, User $staff, array $quantities, array $details = []): Shipment
    {
        $shipment = DB::transaction(function () use ($order, $staff, $quantities, $details): Shipment {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::PartiallyDispatched], true)) {
                throw BusinessRuleException::make('order_not_fulfillable', ['status' => $order->status->label()]);
            }

            $items = OrderItem::query()->where('order_id', $order->id)->lockForUpdate()->get()->keyBy('id');
            $packed = $this->packedNotDispatched($order);

            foreach ($quantities as $itemId => $qty) {
                $item = $items->get($itemId);

                if ($item === null || $qty < 1) {
                    throw BusinessRuleException::make('shipment_item_invalid');
                }

                $available = $item->remainingQuantity() - ($packed[$itemId] ?? 0);

                if ($qty > $available) {
                    throw BusinessRuleException::make('shipment_quantity_exceeds', ['sku' => $item->sku, 'available' => max(0, $available)]);
                }
            }

            $shipment = new Shipment(array_intersect_key($details, array_flip(['carrier', 'tracking_number', 'vehicle_number', 'driver_name', 'driver_phone', 'notes'])));
            $shipment->forceFill([
                'challan_number' => DocumentSequence::next('challan'),
                'order_id' => $order->id,
                'status' => ShipmentStatus::Packing,
                'packed_by' => $staff->id,
            ])->save();

            $shipment->items()->createMany(collect($quantities)->map(fn ($qty, $id) => ['order_item_id' => $id, 'quantity' => $qty])->values()->all());

            AuditLog::record('shipment.created', $shipment, [], ['order' => $order->order_number]);

            return $shipment;
        });

        if ($order->fresh()->status === OrderStatus::Confirmed) {
            $this->orders->transition($order, OrderStatus::Processing, $staff, $shipment->challan_number, notify: false);
        }

        return $shipment;
    }

    public function dispatch(Shipment $shipment, User $staff, array $details = []): Shipment
    {
        $shipment = DB::transaction(function () use ($shipment, $staff, $details): Shipment {
            $order = Order::query()->whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($shipment->status !== ShipmentStatus::Packing) {
                throw BusinessRuleException::make('shipment_invalid_transition', ['status' => $shipment->status->label()]);
            }

            if ($order->status === OrderStatus::Cancelled) {
                throw BusinessRuleException::make('order_not_fulfillable', ['status' => $order->status->label()]);
            }

            $shipment->fill(array_intersect_key($details, array_flip(['carrier', 'tracking_number', 'vehicle_number', 'driver_name', 'driver_phone', 'notes'])));
            $shipment->forceFill(['status' => ShipmentStatus::Dispatched, 'dispatched_by' => $staff->id, 'dispatched_at' => now()])->save();

            foreach ($shipment->items()->get() as $line) {
                OrderItem::query()->whereKey($line->order_item_id)->increment('dispatched_quantity', $line->quantity);
            }

            return $shipment;
        });

        $order = $shipment->order()->with('items')->firstOrFail();
        $target = $order->isFullyDispatched() ? OrderStatus::Dispatched : OrderStatus::PartiallyDispatched;

        if ($order->status !== $target) {
            $this->orders->transition($order, $target, $staff, $shipment->challan_number);
        }

        return $shipment;
    }

    public function markDelivered(Shipment $shipment, User $staff, ?string $receivedBy = null): Shipment
    {
        $shipment = DB::transaction(function () use ($shipment, $receivedBy): Shipment {
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($shipment->status !== ShipmentStatus::Dispatched) {
                throw BusinessRuleException::make('shipment_invalid_transition', ['status' => $shipment->status->label()]);
            }

            $shipment->forceFill(['status' => ShipmentStatus::Delivered, 'delivered_at' => now(), 'received_by_name' => $receivedBy])->save();

            return $shipment;
        });

        $order = $shipment->order()->with(['items', 'shipments'])->firstOrFail();
        $allDelivered = $order->shipments->every(fn (Shipment $s) => $s->status === ShipmentStatus::Delivered);

        if ($order->status === OrderStatus::Dispatched && $allDelivered) {
            $this->orders->transition($order, OrderStatus::Delivered, $staff, $shipment->challan_number);
        }

        return $shipment;
    }

    /** Cancels a packing shipment (frees its quantities for a new challan). */
    public function cancelPacking(Shipment $shipment): void
    {
        DB::transaction(function () use ($shipment): void {
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($shipment->status !== ShipmentStatus::Packing) {
                throw BusinessRuleException::make('shipment_invalid_transition', ['status' => $shipment->status->label()]);
            }

            $shipment->delete();
        });
    }

    /** @return array<int, int> order_item_id => qty sitting in packing shipments */
    private function packedNotDispatched(Order $order): array
    {
        return DB::table('shipment_items')
            ->join('shipments', 'shipments.id', '=', 'shipment_items.shipment_id')
            ->where('shipments.order_id', $order->id)
            ->where('shipments.status', ShipmentStatus::Packing->value)
            ->groupBy('shipment_items.order_item_id')
            ->selectRaw('shipment_items.order_item_id as item_id, SUM(shipment_items.quantity) as qty')
            ->pluck('qty', 'item_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
