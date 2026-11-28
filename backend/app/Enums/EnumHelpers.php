<?php

namespace App\Enums;

trait EnumHelpers
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Localised human label, resolved from lang/{locale}/enums.php. */
    public function label(): string
    {
        return __('enums.'.self::key().'.'.$this->value);
    }

    public static function key(): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (new \ReflectionClass(self::class))->getShortName()));
    }
}
