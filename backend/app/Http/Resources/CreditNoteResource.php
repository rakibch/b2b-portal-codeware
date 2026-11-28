<?php

namespace App\Http\Resources;

use App\Models\CreditNote;
use Illuminate\Http\Request;

/** @mixin CreditNote */
class CreditNoteResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'credit_note_number' => $this->credit_note_number,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->invoice_number),
            'amount' => $this->amount,
            'reason' => $this->reason,
            'status' => $this->enum($this->status),
            'issued_by' => $this->whenLoaded('issuer', fn () => $this->issuer?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
