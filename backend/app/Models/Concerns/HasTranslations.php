<?php

namespace App\Models\Concerns;

/**
 * JSON-column translations: {"en": "...", "bn": "..."}.
 *
 * - `$model->name` returns the active-locale value, falling back to app.fallback_locale (en),
 *   then to any available translation.
 * - `$model->name = 'x'` writes the active locale only (other locales are preserved).
 * - `$model->name = ['en' => 'x', 'bn' => 'y']` replaces all translations (unsupported locales dropped).
 * - `$model->getTranslations('name')` returns the raw map (admin editing / API).
 *
 * Using model must declare: protected array $translatable = ['name', ...];
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(array_fill_keys($this->getTranslatableAttributes(), 'array'));
    }

    /** @return list<string> */
    public function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes(), true);
    }

    public function getAttributeValue($key): mixed
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->getTranslation($key);
    }

    public function setAttribute($key, $value): mixed
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::setAttribute($key, $value);
        }

        if (is_array($value)) {
            return $this->setTranslations($key, $value);
        }

        if ($value === null) {
            return parent::setAttribute($key, null);
        }

        return $this->setTranslation($key, app()->getLocale(), (string) $value);
    }

    /** @return array<string, string> */
    public function getTranslations(string $key): array
    {
        $raw = parent::getAttributeValue($key);

        if (! is_array($raw)) {
            return [];
        }

        return array_filter(
            $raw,
            static fn ($v, $locale) => is_string($locale) && is_scalar($v) && trim((string) $v) !== '',
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function getTranslation(string $key, ?string $locale = null, bool $useFallback = true): ?string
    {
        $translations = $this->getTranslations($key);
        $locale ??= app()->getLocale();

        if (isset($translations[$locale])) {
            return (string) $translations[$locale];
        }

        if (! $useFallback) {
            return null;
        }

        $fallback = config('app.fallback_locale', 'en');

        if (isset($translations[$fallback])) {
            return (string) $translations[$fallback];
        }

        $first = reset($translations);

        return $first === false ? null : (string) $first;
    }

    public function hasTranslation(string $key, string $locale): bool
    {
        return isset($this->getTranslations($key)[$locale]);
    }

    public function setTranslation(string $key, string $locale, ?string $value): static
    {
        $this->assertSupportedLocale($locale);

        $translations = $this->getTranslations($key);

        if ($value === null || trim($value) === '') {
            unset($translations[$locale]);
        } else {
            $translations[$locale] = trim($value);
        }

        parent::setAttribute($key, $translations);

        return $this;
    }

    /** @param array<string, ?string> $translations */
    public function setTranslations(string $key, array $translations): static
    {
        $supported = config('b2b.supported_locales', ['en', 'bn']);
        $clean = [];

        foreach ($translations as $locale => $value) {
            if (in_array($locale, $supported, true) && is_scalar($value) && trim((string) $value) !== '') {
                $clean[$locale] = trim((string) $value);
            }
        }

        parent::setAttribute($key, $clean);

        return $this;
    }

    /** Full translation maps for every translatable attribute (for admin resources). */
    public function getAllTranslations(): array
    {
        $out = [];

        foreach ($this->getTranslatableAttributes() as $attribute) {
            $out[$attribute] = $this->getTranslations($attribute);
        }

        return $out;
    }

    private function assertSupportedLocale(string $locale): void
    {
        if (! in_array($locale, config('b2b.supported_locales', ['en', 'bn']), true)) {
            throw new \InvalidArgumentException("Unsupported locale [{$locale}].");
        }
    }
}
