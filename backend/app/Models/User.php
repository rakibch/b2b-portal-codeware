<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserType;
use App\Support\Money;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Security: company_id, user_type, is_active and order_approval_limit are NOT mass assignable;
 * they are set explicitly by services (registration, invitations, admin user management).
 */
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'designation',
        'password',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'order_approval_limit' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by');
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'placed_by');
    }

    /** Companies this staff user manages as Account Manager. */
    public function managedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'account_manager_id');
    }

    public function isStaff(): bool
    {
        return $this->user_type === UserType::Staff;
    }

    public function isBuyer(): bool
    {
        return $this->user_type === UserType::Buyer;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin->value);
    }

    public function isBuyerAdmin(): bool
    {
        return $this->hasRole(Role::BuyerAdmin->value);
    }

    /** Buyer belongs to an approved company and is active. */
    public function canTransact(): bool
    {
        return $this->is_active
            && $this->isBuyer()
            && $this->company !== null
            && $this->company->isApproved();
    }

    /** Wholesale prices are visible to staff and to buyers of KYC-approved companies only. */
    public function canSeePrices(): bool
    {
        return $this->is_active && ($this->isStaff() || $this->canTransact());
    }

    /** True when this sub-user's order total requires Buyer Admin approval. */
    public function requiresApprovalFor(string|float $orderTotal): bool
    {
        if ($this->isBuyerAdmin() || $this->order_approval_limit === null) {
            return false;
        }

        return Money::compare($orderTotal, (string) $this->order_approval_limit) > 0;
    }

    /** Same-company check — the core tenant isolation primitive for policies. */
    public function belongsToCompany(?int $companyId): bool
    {
        return $companyId !== null && $this->company_id === $companyId;
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('user_type', UserType::Staff->value);
    }

    public function scopeBuyers(Builder $query): Builder
    {
        return $query->where('user_type', UserType::Buyer->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function preferredLocale(): string
    {
        return $this->locale ?: config('app.locale');
    }
}
