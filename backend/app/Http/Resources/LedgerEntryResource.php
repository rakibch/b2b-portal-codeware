<?php

namespace App\Http\Resources;

use App\Models\LedgerEntry;
use Illuminate\Http\Request;

/** @mixin LedgerEntry */
class LedgerEntryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->entry_date?->toDateString(),
            'type' => $this->enum($this->type),
            'reference' => $this->reference,
            'description' => $this->description,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'balance' => $this->balance,
        ];
    }
}
