<?php

namespace App\Support;

final class PartnerBalance
{
    public static function fromTotals(int|string $sent, int|string $received): array
    {
        $sent = ltrim((string) $sent, '0') ?: '0';
        $received = ltrim((string) $received, '0') ?: '0';
        $comparison = strlen($sent) <=> strlen($received);
        if ($comparison === 0) $comparison = strcmp($sent, $received) <=> 0;

        return [
            'label' => $comparison > 0 ? 'Net sent' : ($comparison < 0 ? 'Net received' : 'Balanced'),
            'amount' => $comparison >= 0
                ? RupeeAmount::difference($sent, $received)
                : RupeeAmount::difference($received, $sent),
        ];
    }
}
