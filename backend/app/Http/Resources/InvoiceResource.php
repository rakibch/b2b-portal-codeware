<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;

/** @mixin Invoice */
class InvoiceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'invoice_number' => $this->invoice_number,
            'order_number' => $this->whenLoaded('order', fn () => $this->order->order_number),
            'company' => $this->when($this->viewerIsStaff($request) && $this->relationLoaded('company'), fn () => ['id' => $this->company->public_id, 'name' => $this->company->name]),
            'status' => $this->enum($this->status),
            'is_overdue' => $this->isOverdue(),
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'vat_total' => $this->vat_total,
            'shipping_total' => $this->shipping_total,
            'grand_total' => $this->grand_total,
            'amount_paid' => $this->amount_paid,
            'amount_credited' => $this->amount_credited,
            'balance_due' => $this->balanceDue(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'pdf_path' => '/api/v1/invoices/'.$this->invoice_number.'/pdf',
        ];
    }
}
