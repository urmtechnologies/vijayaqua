<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class RupeeAmount
{
    // Below PHP's integer limit and JavaScript's exact-integer limit.
    public const MAX = 9_000_000_000_000_000;

    public static function int(int|string $value, string $field): int
    {
        if (! preg_match('/^(0|[1-9][0-9]*)$/', (string) $value)
            || strlen((string) $value) > 16
            || (int) $value > self::MAX) {
            throw ValidationException::withMessages([$field => 'Enter a valid whole-rupee amount.']);
        }

        return (int) $value;
    }

    public static function add(int $a, int $b, string $field): int
    {
        if ($b > self::MAX - $a) {
            throw ValidationException::withMessages([$field => 'Amount exceeds the supported limit.']);
        }

        return $a + $b;
    }

    public static function multiply(int $quantity, int $rate, string $field): int
    {
        if ($rate && $quantity > intdiv(self::MAX, $rate)) {
            throw ValidationException::withMessages([$field => 'Line amount exceeds the supported limit.']);
        }

        return $quantity * $rate;
    }

    public static function format(int|string|null $value): string
    {
        return CartonNumber::format($value);
    }

    public static function difference(int|string $total, int|string $paid): string
    {
        $a = ltrim((string) $total, '0') ?: '0';
        $b = ltrim((string) $paid, '0') ?: '0';
        if (strlen($b) > strlen($a) || (strlen($a) === strlen($b) && strcmp($b, $a) > 0)) {
            throw new \LogicException('Payments exceed the invoice total.');
        }

        $result = '';
        $borrow = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            $left = (int) $a[strlen($a) - 1 - $i] - $borrow;
            $right = $i < strlen($b) ? (int) $b[strlen($b) - 1 - $i] : 0;
            $borrow = $left < $right ? 1 : 0;
            $result = (string) ($left + ($borrow ? 10 : 0) - $right).$result;
        }

        return ltrim($result, '0') ?: '0';
    }
}
