<?php

namespace App\Support;

use App\Enums\Unit;

/**
 * Formatowanie liczb w konwencji polskiej (przecinek dziesiętny, spacja tysięcy)
 * oraz bezpieczna normalizacja danych wejściowych do arytmetyki bcmath.
 */
final class Num
{
    private const THIN_SPACE = "\u{202F}";

    public static function qty(string|int|float|null $value, Unit|string|null $unit = null): string
    {
        $value = (string) ($value ?? '0');
        $decimals = self::significantDecimals($value, 3);
        $formatted = number_format((float) $value, $decimals, ',', self::THIN_SPACE);

        if ($unit === null) {
            return $formatted;
        }

        return $formatted.' '.($unit instanceof Unit ? $unit->value : $unit);
    }

    public static function money(string|int|float|null $value, bool $withCurrency = true): string
    {
        $formatted = number_format((float) ($value ?? 0), 2, ',', self::THIN_SPACE);

        return $withCurrency ? $formatted.' zł' : $formatted;
    }

    public static function signedQty(string|int|float $value): string
    {
        $prefix = bccomp((string) $value, '0', 3) > 0 ? '+' : '';

        return $prefix.self::qty($value);
    }

    /** Polska odmiana: plural(3, 'konto', 'konta', 'kont') → „3 konta”. */
    public static function plural(int $count, string $one, string $few, string $many): string
    {
        $word = match (true) {
            $count === 1 => $one,
            $count % 10 >= 2 && $count % 10 <= 4 && ($count % 100 < 12 || $count % 100 > 14) => $few,
            default => $many,
        };

        return "{$count} {$word}";
    }

    /** Wartość do pola formularza: „2,5” zamiast „2.500”, pusty łańcuch dla null. */
    public static function input(string|int|float|null $value, int $maxDecimals = 3): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (string) $value;
        $decimals = self::significantDecimals($value, $maxDecimals);

        return number_format((float) $value, $decimals, ',', '');
    }

    /**
     * Zamienia wpis użytkownika („1 234,5”) na łańcuch zgodny z bcmath („1234.5”).
     * Zwraca null dla wartości nieliczbowych.
     */
    public static function parse(string|int|float|null $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $normalized = str_replace([' ', self::THIN_SPACE, "\u{00A0}", ','], ['', '', '', '.'], trim((string) $input));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return bcadd($normalized, '0', 4);
    }

    private static function significantDecimals(string $value, int $max): int
    {
        if (! str_contains($value, '.')) {
            return 0;
        }

        $fraction = rtrim(substr($value, strpos($value, '.') + 1), '0');

        return min(strlen($fraction), $max);
    }
}
