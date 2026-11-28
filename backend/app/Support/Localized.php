<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Bangladesh-style formatting for documents: South-Asian digit grouping (12,34,567.00),
 * Bangla numerals for `bn`, ৳ currency, localised dates and amount-in-words (Taka/Poisha).
 */
final class Localized
{
    private const BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    private const BN_WORDS = [
        'শূন্য', 'এক', 'দুই', 'তিন', 'চার', 'পাঁচ', 'ছয়', 'সাত', 'আট', 'নয়',
        'দশ', 'এগারো', 'বারো', 'তেরো', 'চৌদ্দ', 'পনেরো', 'ষোলো', 'সতেরো', 'আঠারো', 'উনিশ',
        'বিশ', 'একুশ', 'বাইশ', 'তেইশ', 'চব্বিশ', 'পঁচিশ', 'ছাব্বিশ', 'সাতাশ', 'আটাশ', 'উনত্রিশ',
        'ত্রিশ', 'একত্রিশ', 'বত্রিশ', 'তেত্রিশ', 'চৌত্রিশ', 'পঁয়ত্রিশ', 'ছত্রিশ', 'সাঁইত্রিশ', 'আটত্রিশ', 'উনচল্লিশ',
        'চল্লিশ', 'একচল্লিশ', 'বিয়াল্লিশ', 'তেতাল্লিশ', 'চুয়াল্লিশ', 'পঁয়তাল্লিশ', 'ছেচল্লিশ', 'সাতচল্লিশ', 'আটচল্লিশ', 'উনপঞ্চাশ',
        'পঞ্চাশ', 'একান্ন', 'বায়ান্ন', 'তিপ্পান্ন', 'চুয়ান্ন', 'পঞ্চান্ন', 'ছাপ্পান্ন', 'সাতান্ন', 'আটান্ন', 'উনষাট',
        'ষাট', 'একষট্টি', 'বাষট্টি', 'তেষট্টি', 'চৌষট্টি', 'পঁয়ষট্টি', 'ছেষট্টি', 'সাতষট্টি', 'আটষট্টি', 'উনসত্তর',
        'সত্তর', 'একাত্তর', 'বাহাত্তর', 'তিয়াত্তর', 'চুয়াত্তর', 'পঁচাত্তর', 'ছিয়াত্তর', 'সাতাত্তর', 'আটাত্তর', 'উনআশি',
        'আশি', 'একাশি', 'বিরাশি', 'তিরাশি', 'চুরাশি', 'পঁচাশি', 'ছিয়াশি', 'সাতাশি', 'আটাশি', 'উননব্বই',
        'নব্বই', 'একানব্বই', 'বিরানব্বই', 'তিরানব্বই', 'চুরানব্বই', 'পঁচানব্বই', 'ছিয়ানব্বই', 'সাতানব্বই', 'আটানব্বই', 'নিরানব্বই',
    ];

    private const EN_ONES = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const EN_TENS = [2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty', 6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'];

    public static function digits(string|int $value, ?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'bn'
            ? strtr((string) $value, array_combine(range(0, 9), self::BN_DIGITS))
            : (string) $value;
    }

    /** 1234567.5 → "12,34,567.50" (bn: "১২,৩৪,৫৬৭.৫০"). */
    public static function number(string|int|float|null $amount, ?string $locale = null, int $decimals = 2): string
    {
        $value = BigDecimal::of(is_float($amount) ? number_format($amount, 6, '.', '') : (string) ($amount ?? '0'))
            ->toScale($decimals, RoundingMode::HalfUp);

        $negative = $value->isNegative();
        [$int, $frac] = array_pad(explode('.', (string) $value->abs()), 2, '');

        if (strlen($int) > 3) {
            $last3 = substr($int, -3);
            $rest = substr($int, 0, -3);
            $int = ltrim(strrev(implode(',', str_split(strrev($rest), 2))), ',').','.$last3;
        }

        $formatted = ($negative ? '-' : '').$int.($decimals > 0 ? '.'.$frac : '');

        return self::digits($formatted, $locale);
    }

    public static function currency(string|int|float|null $amount, ?string $locale = null): string
    {
        return config('b2b.currency.symbol', '৳').' '.self::number($amount, $locale);
    }

    /** HTML-safe currency for PDFs: the ৳ glyph is forced into a font that contains it. */
    public static function currencyHtml(string|int|float|null $amount, ?string $locale = null): string
    {
        return '<span class="taka">'.e(config('b2b.currency.symbol', '৳')).'</span>&nbsp;'.e(self::number($amount, $locale));
    }

    public static function date(DateTimeInterface|string|null $date, ?string $locale = null, string $format = 'd F Y'): string
    {
        if ($date === null) {
            return '—';
        }

        $locale ??= app()->getLocale();

        return self::digits(Carbon::parse($date)->locale($locale)->translatedFormat($format), $locale);
    }

    /** "Taka Twelve Lakh Thirty-Four Thousand ... and Fifty Poisha Only" / "বারো লক্ষ ... টাকা পঞ্চাশ পয়সা মাত্র". */
    public static function amountInWords(string|int|float|null $amount, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $value = BigDecimal::of((string) ($amount ?? '0'))->abs()->toScale(2, RoundingMode::HalfUp);
        [$taka, $poisha] = array_map('intval', explode('.', (string) $value));

        if ($locale === 'bn') {
            $words = self::bnWords($taka).' টাকা';

            return $words.($poisha > 0 ? ' '.self::BN_WORDS[$poisha].' পয়সা' : '').' মাত্র';
        }

        $words = 'Taka '.ucwords(self::enWords($taka));

        return $words.($poisha > 0 ? ' and '.ucwords(self::enWords($poisha)).' Poisha' : '').' Only';
    }

    private static function bnWords(int $n): string
    {
        if ($n < 100) {
            return self::BN_WORDS[$n];
        }

        $parts = [];
        foreach ([[10000000, 'কোটি'], [100000, 'লক্ষ'], [1000, 'হাজার'], [100, 'শত']] as [$unit, $label]) {
            if ($n >= $unit) {
                $count = intdiv($n, $unit);
                $parts[] = ($unit === 10000000 ? self::bnWords($count) : self::BN_WORDS[$count]).' '.$label;
                $n %= $unit;
            }
        }

        if ($n > 0) {
            $parts[] = self::BN_WORDS[$n];
        }

        return implode(' ', $parts);
    }

    private static function enWords(int $n): string
    {
        if ($n < 20) {
            return self::EN_ONES[$n];
        }

        if ($n < 100) {
            return self::EN_TENS[intdiv($n, 10)].($n % 10 ? '-'.self::EN_ONES[$n % 10] : '');
        }

        $parts = [];
        foreach ([[10000000, 'crore'], [100000, 'lakh'], [1000, 'thousand'], [100, 'hundred']] as [$unit, $label]) {
            if ($n >= $unit) {
                $parts[] = self::enWords(intdiv($n, $unit)).' '.$label;
                $n %= $unit;
            }
        }

        if ($n > 0) {
            $parts[] = self::enWords($n);
        }

        return implode(' ', $parts);
    }
}
