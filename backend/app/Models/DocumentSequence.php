<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Race-safe yearly document numbering: e.g. ORD-2026-000042. */
class DocumentSequence extends Model
{
    protected $fillable = ['type', 'year', 'last_number'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }

    public static function next(string $type): string
    {
        $prefix = config("b2b.sequences.{$type}");

        if ($prefix === null) {
            throw new InvalidArgumentException("Unknown document sequence [{$type}].");
        }

        $year = (int) now()->format('Y');

        $number = DB::transaction(function () use ($type, $year): int {
            // Ensure row exists (unique(type, year) makes concurrent inserts safe).
            self::query()->insertOrIgnore([
                'type' => $type,
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = self::query()
                ->where('type', $type)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return $sequence->last_number;
        });

        return sprintf('%s-%d-%06d', $prefix, $year, $number);
    }
}
