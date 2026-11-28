<?php

namespace App\Support;

/** Builds {"en": ..., "bn": ...} payloads from a translation key (for stored, translatable text). */
final class Bilingual
{
    /**
     * Replacement values may themselves be per-locale maps, e.g. ['method' => ['en' => 'Cash', 'bn' => 'নগদ']].
     *
     * @return array<string, string>
     */
    public static function trans(string $key, array $replace = []): array
    {
        $out = [];

        foreach (config('b2b.supported_locales', ['en', 'bn']) as $locale) {
            $localised = array_map(fn ($v) => is_array($v) ? ($v[$locale] ?? $v['en'] ?? '') : $v, $replace);
            $out[$locale] = __($key, $localised, $locale);
        }

        return $out;
    }
}
