<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Exact decimal arithmetic for money (2dp, HALF_UP). Never use floats for currency.
 * Backed by brick/math (shipped with Laravel), so no bcmath extension is required.
 */
final class Money
{
    public const SCALE = 2;

    public static function of(string|int|float|null $amount): BigDecimal
    {
        return BigDecimal::of(self::normalise($amount));
    }

    public static function add(string|int|float|null ...$amounts): string
    {
        $sum = BigDecimal::zero();

        foreach ($amounts as $amount) {
            $sum = $sum->plus(self::normalise($amount));
        }

        return self::format($sum);
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::format(self::of($a)->minus(self::normalise($b)));
    }

    public static function mul(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::format(self::of($a)->multipliedBy(self::normalise($b)));
    }

    /** Percentage of an amount, e.g. VAT: percent('1000', '15') => '150.00'. */
    public static function percent(string|int|float|null $amount, string|int|float|null $percent): string
    {
        return self::format(self::of($amount)->multipliedBy(self::normalise($percent))->dividedBy(100, 6, RoundingMode::HalfUp));
    }

    public static function compare(string|int|float|null $a, string|int|float|null $b): int
    {
        return self::of($a)->compareTo(self::normalise($b));
    }

    public static function isPositive(string|int|float|null $amount): bool
    {
        return self::of($amount)->isPositive();
    }

    public static function max(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::compare($a, $b) >= 0 ? self::format(self::of($a)) : self::format(self::of($b));
    }

    public static function format(BigDecimal $value): string
    {
        return (string) $value->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    private static function normalise(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0';
        }

        // Floats are formatted deterministically before parsing to avoid binary artefacts.
        return is_float($amount) ? number_format($amount, 6, '.', '') : (string) $amount;
    }
}
