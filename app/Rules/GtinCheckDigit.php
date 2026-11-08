<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cyfra kontrolna kodów GS1 (EAN-8, UPC-A, EAN-13, GTIN-14).
 * Od prawej, z pominięciem cyfry kontrolnej, cyfry mają na przemian wagi 3 i 1.
 */
class GtinCheckDigit implements ValidationRule
{
    public static function isValid(string $code): bool
    {
        if (! preg_match('/^(\d{8}|\d{12,14})$/', $code)) {
            return false;
        }

        $digits = array_map('intval', str_split($code));
        $check = array_pop($digits);
        $sum = 0;
        foreach (array_reverse($digits) as $i => $digit) {
            $sum += $digit * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === $check;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('/^(\d{8}|\d{12,14})$/', $value) && ! self::isValid($value)) {
            $fail('Nieprawidłowa cyfra kontrolna kodu EAN. Sprawdź, czy kod został przepisany bez pomyłki.');
        }
    }
}
