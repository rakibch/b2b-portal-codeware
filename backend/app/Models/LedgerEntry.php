<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/** Append-only. Corrections are made with reversal/adjustment entries, never updates or deletes. */
class LedgerEntry extends Model
{
    use HasTranslations;

    public const UPDATED_AT = null;

    protected array $translatable = ['description'];

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'entry_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are immutable.'));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('entry_date')->orderBy('id');
    }
}
