<?php

namespace App\Http\Resources;

use App\Enums\OrderApprovalStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;

/** @mixin Order */
class OrderResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $staff = $this->viewerIsStaff($request);
        $user = $request->user();

        return [
            'order_number' => $this->order_number,
            'status' => $this->enum($this->status),
            'approval_status' => $this->enum($this->approval_status),
            'payment_method' => $this->enum($this->payment_method),
            'payment_status' => $this->enum($this->payment_status),
            'company' => $this->when($staff && $this->relationLoaded('company'), fn () => ['id' => $this->company->public_id, 'name' => $this->company->name]),
            'placed_by' => $this->whenLoaded('placedBy', fn () => $this->placedBy?->only(['id', 'name'])),
            'created_by_staff' => $this->whenLoaded('createdByStaff', fn () => $this->createdByStaff?->only(['id', 'name'])),
            'approved_by' => $this->whenLoaded('approver', fn () => $this->approver?->only(['id', 'name'])),
            'rfq_reference' => $this->whenLoaded('rfq', fn () => $this->rfq?->reference),
            'po_number' => $this->po_number,
            'buyer_notes' => $this->buyer_notes,
            'internal_notes' => $this->when($staff, $this->internal_notes),
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'totals' => [
                'subtotal' => $this->subtotal,
                'discount_total' => $this->discount_total,
                'vat_total' => $this->vat_total,
                'shipping_total' => $this->shipping_total,
                'grand_total' => $this->grand_total,
                'amount_paid' => $this->amount_paid,
            ],
            'currency' => $this->currency,
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'sku' => $i->sku,
                'name' => $i->product_name,
                'unit' => $i->unit_name,
                'pack_size' => $i->pack_size,
                'quantity' => $i->quantity,
                'dispatched_quantity' => $i->dispatched_quantity,
                'remaining_quantity' => $i->remainingQuantity(),
                'list_price' => $i->list_price,
                'unit_price' => $i->unit_price,
                'price_source' => $this->enum($i->price_source),
                'vat_percent' => $i->vat_percent,
                'line_subtotal' => $i->line_subtotal,
                'discount_amount' => $i->discount_amount,
                'vat_amount' => $i->vat_amount,
                'line_total' => $i->line_total,
            ])),
            'history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'from' => $this->enum($h->from_status),
                'to' => $this->enum($h->to_status),
                'by' => $h->relationLoaded('changer') ? $h->changer?->name : null,
                'comment' => $h->comment,
                'at' => $h->created_at?->toIso8601String(),
            ])),
            'shipments' => ShipmentResource::collection($this->whenLoaded('shipments')),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? new InvoiceResource($this->invoice) : null),
            'actions' => $this->when($user !== null && $this->relationLoaded('items'), fn () => [
                'can_cancel' => $user->can('cancel', $this->resource),
                'can_approve' => $this->approval_status === OrderApprovalStatus::Pending && $user->can('approve', $this->resource),
                'can_reorder' => $user->can('reorder', $this->resource),
                'can_pay_online' => $this->payment_method->value === 'gateway' && $this->status !== OrderStatus::Cancelled && $this->payment_status->value !== 'paid' && $user->can('pay', $this->resource),
            ]),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
