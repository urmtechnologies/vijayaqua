<?php

namespace App\Support;

final class CartonNumber
{
    public static function format(int|string|null $value): string
    {
        $digits = (string) ($value ?? 0);

        return preg_match('/^\d+$/', $digits)
            ? preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits)
            : '0';
    }
}
