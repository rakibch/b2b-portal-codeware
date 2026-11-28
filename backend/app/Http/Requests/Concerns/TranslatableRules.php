<?php

namespace App\Http\Requests\Concerns;

trait TranslatableRules
{
    /** Rules for a {"en": "...", "bn": "..."} input. English is the canonical/fallback locale. */
    protected function translatable(string $field, bool $required = true, int $max = 255, bool $sometimes = false): array
    {
        $prefix = $sometimes ? ['sometimes'] : [];
        $presence = $required ? 'required' : 'nullable';

        return [
            $field => [...$prefix, $presence, 'array:en,bn'],
            "{$field}.en" => [...$prefix, $presence, 'string', 'max:'.$max],
            "{$field}.bn" => ['nullable', 'string', 'max:'.$max],
        ];
    }
}
