<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Records a security/business-relevant event with request context. */
    public static function record(string $event, ?Model $auditable = null, array $old = [], array $new = []): self
    {
        $request = request();

        return self::query()->create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 512) : null,
        ]);
    }
}
