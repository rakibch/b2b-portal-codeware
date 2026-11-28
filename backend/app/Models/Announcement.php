<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'body'];

    protected $fillable = ['title', 'body', 'audience', 'level', 'is_published', 'published_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, AnnouncementAudience $audience): Builder
    {
        return $query->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('audience', [AnnouncementAudience::All->value, $audience->value])
            ->latest('published_at');
    }
}
