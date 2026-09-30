<?php

namespace App\Support;

use App\Models\{VehicleEntry, VehiclePayment};

final class VehicleWallet
{
    public static function totals(int $referenceUserId): array
    {
        $charged = SaleMoney::paise((string) VehicleEntry::query()->where('reference_user_id', $referenceUserId)
            ->where('approval_status', 'approved')->sum('amount_rupees'));
        $payments = VehiclePayment::query()->where('reference_user_id', $referenceUserId);
        $credit = SaleMoney::paise((string) (clone $payments)->where('entry_type', 'credit')->sum('amount_rupees'));
        $debit = SaleMoney::paise((string) (clone $payments)->where('entry_type', 'debit')->sum('amount_rupees'));

        return ['charged' => $charged, 'paid' => $credit - $debit, 'balance' => $charged - $credit + $debit];
    }
}
