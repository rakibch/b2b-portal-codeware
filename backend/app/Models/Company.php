<?php

namespace App\Models;

use App\Enums\AddressType;
use App\Enums\BusinessType;
use App\Enums\InvoiceStatus;
use App\Enums\KycStatus;
use App\Models\Concerns\HasPublicUlid;
use App\Support\Money;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Security: KYC status, credit terms, outstanding balance, group and account manager are NOT
 * mass assignable. They change only through KycService / CreditLedgerService using forceFill().
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, HasPublicUlid, SoftDeletes;

    protected $fillable = [
        'name',
        'trade_name',
        'business_type',
        'contact_person',
        'email',
        'phone',
        'trade_license_no',
        'tin_no',
        'bin_no',
        'website',
    ];

    protected $hidden = ['id', 'internal_notes'];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'kyc_status' => KycStatus::class,
            'kyc_submitted_at' => 'datetime',
            'kyc_reviewed_at' => 'datetime',
            'credit_limit' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'credit_enabled' => 'boolean',
            'credit_on_hold' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function kycReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kyc_reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CompanyAddress::class);
    }

    public function shippingAddresses(): HasMany
    {
        return $this->addresses()->where('type', AddressType::Shipping->value);
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(CompanyAddress::class)
            ->where('type', AddressType::Billing->value)
            ->orderByDesc('is_default')
            ->latestOfMany();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(CompanyProductPrice::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class);
    }

    public function isApproved(): bool
    {
        return $this->kyc_status === KycStatus::Approved;
    }

    /** available_credit = credit_limit - outstanding_balance (never negative). */
    public function availableCredit(): string
    {
        if (! $this->credit_enabled || $this->credit_on_hold) {
            return '0.00';
        }

        return Money::max(Money::sub((string) $this->credit_limit, (string) $this->outstanding_balance), '0');
    }

    public function overdueInvoices(): HasMany
    {
        return $this->invoices()
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::PartiallyPaid->value])
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('kyc_status', KycStatus::Approved->value);
    }

    public function scopePendingKyc(Builder $query): Builder
    {
        return $query->where('kyc_status', KycStatus::Pending->value);
    }

    /** Restricts to companies a Sales Manager is assigned to. */
    public function scopeManagedBy(Builder $query, User $manager): Builder
    {
        return $query->where('account_manager_id', $manager->id);
    }
}
