<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class SaleMoney
{
    // Calculations use integer paise so quantity × rate never loses .10 or .01.
    public const MAX_PAISE = 9_000_000_000_000_000;

    public static function paise(int|string $value, string $field = 'amount'): int
    {
        $value = (string) $value;
        if (! preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/', $value, $match)) {
            throw ValidationException::withMessages([$field => 'Enter a valid amount with up to two decimal places.']);
        }
        $rupees = $match[1];
        if (strlen($rupees) > 13 || (int) $rupees > intdiv(self::MAX_PAISE, 100)) {
            throw ValidationException::withMessages([$field => 'Amount exceeds the supported limit.']);
        }
        $result = (int) $rupees * 100 + (int) str_pad($match[2] ?? '', 2, '0');
        if ($result > self::MAX_PAISE) {
            throw ValidationException::withMessages([$field => 'Amount exceeds the supported limit.']);
        }
        return $result;
    }

    public static function decimal(int $paise): string
    {
        return ($paise < 0 ? '-' : '').intdiv(abs($paise), 100).'.'.str_pad((string) (abs($paise) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(int|string|null $value): string
    {
        $paise = self::paise((string) ($value ?? '0'));
        return number_format(intdiv($paise, 100), 0).($paise % 100 ? '.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT) : '');
    }

    public static function add(int $a, int $b, string $field = 'amount'): int
    {
        if ($b > self::MAX_PAISE - $a) {
            throw ValidationException::withMessages([$field => 'Amount exceeds the supported limit.']);
        }
        return $a + $b;
    }

    public static function multiply(int $quantity, int $rate, string $field): int
    {
        if ($rate && $quantity > intdiv(self::MAX_PAISE, $rate)) {
            throw ValidationException::withMessages([$field => 'Line amount exceeds the supported limit.']);
        }
        return $quantity * $rate;
    }
}
