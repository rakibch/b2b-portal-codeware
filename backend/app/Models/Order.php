<?php

namespace App\Models;

use App\Enums\OrderApprovalStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Orders are created/mutated exclusively by OrderService (no mass assignment from requests). */
class Order extends Model
{
    use SoftDeletes;

    protected $fillable = ['po_number', 'buyer_notes'];

    protected $hidden = ['internal_notes'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'approval_status' => OrderApprovalStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => OrderPaymentStatus::class,
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'approved_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_staff_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function shippingAddressRecord(): BelongsTo
    {
        return $this->belongsTo(CompanyAddress::class, 'shipping_address_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isCancellable(): bool
    {
        return $this->status->isCancellable();
    }

    public function isFullyDispatched(): bool
    {
        return $this->items->every(fn (OrderItem $item) => $item->dispatched_quantity >= $item->quantity);
    }

    /**
     * Buyers: own company. Super Admin: all. Warehouse (orders.fulfil without companies.view):
     * confirmed-and-later orders. Sales: their accounts + unassigned accounts.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isBuyer()) {
            return $query->where('company_id', $user->company_id);
        }

        if ($user->can('companies.manage')) {
            return $query;
        }

        if ($user->can('companies.view')) {
            return $query->whereHas('company', fn (Builder $c) => $c->where('account_manager_id', $user->id)->orWhereNull('account_manager_id'));
        }

        if ($user->can('orders.fulfil')) {
            return $query->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value]);
        }

        return $query->whereRaw('1 = 0');
    }

    public function isVisibleTo(User $user): bool
    {
        return self::query()->whereKey($this->id)->visibleTo($user)->exists();
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeStatus(Builder $query, OrderStatus ...$statuses): Builder
    {
        return $query->whereIn('status', array_map(fn (OrderStatus $s) => $s->value, $statuses));
    }

    /** Orders visible to the warehouse (confirmed and later, not cancelled/delivered). */
    public function scopeFulfillable(Builder $query): Builder
    {
        return $this->scopeStatus($query, OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::PartiallyDispatched);
    }
}
