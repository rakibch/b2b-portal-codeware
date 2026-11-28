<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;

/** @mixin Payment */
class PaymentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $staff = $this->viewerIsStaff($request);

        return [
            'reference' => $this->reference,
            'company' => $this->when($staff && $this->relationLoaded('company'), fn () => ['id' => $this->company->public_id, 'name' => $this->company->name]),
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'method' => $this->enum($this->method),
            'status' => $this->enum($this->status),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_ref' => $this->transaction_ref,
            'bank_name' => $this->bank_name,
            'cheque_number' => $this->cheque_number,
            'deposit_date' => $this->deposit_date?->toDateString(),
            'gateway' => $this->gateway,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'has_slip' => $this->hasSlip(),
            'slip_path' => $this->when($this->hasSlip(), '/api/v1/payments/'.$this->reference.'/slip'),
            'submitted_by' => $this->whenLoaded('submitter', fn () => $this->submitter?->only(['id', 'name'])),
            'verified_by' => $this->whenLoaded('verifier', fn () => $this->verifier?->only(['id', 'name'])),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($a) => [
                'invoice_number' => $a->relationLoaded('invoice') ? $a->invoice->invoice_number : null,
                'amount' => $a->amount,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
