<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CompanyInvitation extends Model
{
    protected $fillable = ['email', 'name', 'role', 'order_approval_limit', 'invited_by', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'order_approval_limit' => 'decimal:2',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** Generates a token, stores only its hash, returns the plain token (email it once). */
    public function issueToken(): string
    {
        $plain = Str::random(64);
        $this->token_hash = self::hashToken($plain);

        return $plain;
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function findValidByToken(string $plain): ?self
    {
        return self::query()->valid()->where('token_hash', self::hashToken($plain))->first();
    }

    public function isValid(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
