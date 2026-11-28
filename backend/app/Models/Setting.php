<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    private const CACHE_KEY = 'settings.all';

    protected $fillable = ['group', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Cached read of a setting value (values are JSON-encoded so any type round-trips). */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => self::query()->pluck('value', 'key')->all(),
        );

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general'): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }
}
