<?php

namespace App\Models;

use App\Enums\PaymentRecordMethod;
use App\Enums\PaymentRecordStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'transaction_ref',
        'bank_name',
        'cheque_number',
        'deposit_date',
        'notes',
    ];

    protected $hidden = ['slip_disk', 'slip_path', 'gateway_payload'];

    protected function casts(): array
    {
        return [
            'method' => PaymentRecordMethod::class,
            'status' => PaymentRecordStatus::class,
            'amount' => 'decimal:2',
            'deposit_date' => 'date',
            'gateway_payload' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations')->withPivot('amount')->withTimestamps();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function hasSlip(): bool
    {
        return $this->slip_path !== null;
    }

    public function scopePendingVerification(Builder $query): Builder
    {
        return $query->where('status', PaymentRecordStatus::Pending->value);
    }
}
