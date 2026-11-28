<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CompanyDocument extends Model
{
    protected $fillable = [
        'type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'uploaded_by',
    ];

    // Never expose storage internals to clients.
    protected $hidden = ['disk', 'path', 'checksum'];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (self $document): void {
            Storage::disk($document->disk)->delete($document->path);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
