<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    protected $fillable = ['reason'];

    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'amount' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'credit_note_number';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
