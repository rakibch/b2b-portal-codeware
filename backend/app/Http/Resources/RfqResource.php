<?php

namespace App\Http\Resources;

use App\Enums\RfqStatus;
use App\Models\Rfq;
use App\Support\Money;
use Illuminate\Http\Request;

/** @mixin Rfq */
class RfqResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $staff = $this->viewerIsStaff($request);
        $itemsLoaded = $this->relationLoaded('items');

        return [
            'reference' => $this->reference,
            'status' => $this->enum($this->status),
            'company' => $this->when($staff && $this->relationLoaded('company'), fn () => [
                'id' => $this->company->public_id,
                'name' => $this->company->name,
            ]),
            'requested_by' => $this->whenLoaded('requester', fn () => $this->requester?->only(['id', 'name'])),
            'assigned_to' => $this->when($staff && $this->relationLoaded('assignee'), fn () => $this->assignee?->only(['id', 'name'])),
            'buyer_notes' => $this->buyer_notes,
            'quote_notes' => $this->quote_notes,
            'internal_notes' => $this->when($staff, $this->internal_notes),
            'valid_until' => $this->valid_until?->toDateString(),
            'is_expired' => $this->isExpired(),
            'can_accept' => $this->isAcceptable(),
            'can_cancel' => in_array($this->status, [RfqStatus::Submitted, RfqStatus::UnderReview], true),
            'quoted_at' => $this->quoted_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'converted_order_id' => $this->converted_order_id,
            'items_count' => $this->whenCounted('items'),
            'items' => $this->when($itemsLoaded, fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product' => $item->relationLoaded('product') && $item->product ? [
                    'id' => $item->product->id,
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                    'unit' => $item->product->relationLoaded('unit') ? $item->product->unit->short_name : null,
                    'list_price' => $item->product->base_price,
                ] : null,
                'quantity' => $item->quantity,
                'target_unit_price' => $item->target_unit_price,
                'quoted_unit_price' => $item->quoted_unit_price,
                'quoted_line_total' => $item->quoted_unit_price !== null ? Money::mul((string) $item->quoted_unit_price, $item->quantity) : null,
                'notes' => $item->notes,
            ])),
            'quoted_total' => $this->when($itemsLoaded, fn () => $this->items->every(fn ($i) => $i->quoted_unit_price !== null)
                ? Money::add(...$this->items->map(fn ($i) => Money::mul((string) $i->quoted_unit_price, $i->quantity))->all())
                : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
