<?php

namespace App\Models;

use App\Enums\RfqStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rfq extends Model
{
    protected $fillable = ['buyer_notes'];

    protected $hidden = ['internal_notes'];

    protected function casts(): array
    {
        return [
            'status' => RfqStatus::class,
            'valid_until' => 'date',
            'quoted_at' => 'datetime',
            'responded_at' => 'datetime',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function quoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quoted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class);
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    /** Buyer can accept only an unexpired quote. */
    public function isAcceptable(): bool
    {
        return $this->status === RfqStatus::Quoted && ! $this->isExpired();
    }

    /** Buyers: own company. Super Admin: all. Sales: assigned to them / their accounts / unassigned. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isBuyer()) {
            return $query->where('company_id', $user->company_id);
        }

        if ($user->can('companies.manage')) {
            return $query;
        }

        if (! $user->can('rfqs.view')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('assigned_to', $user->id)
                ->orWhereHas('company', fn (Builder $c) => $c->where('account_manager_id', $user->id))
                ->orWhere(fn (Builder $u) => $u->whereNull('assigned_to')->whereHas('company', fn (Builder $c) => $c->whereNull('account_manager_id')));
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return self::query()->whereKey($this->id)->visibleTo($user)->exists();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RfqStatus::Submitted->value,
            RfqStatus::UnderReview->value,
            RfqStatus::Quoted->value,
        ]);
    }
}
