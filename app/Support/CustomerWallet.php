<?php

namespace App\Support;

use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Support\Facades\DB;

final class CustomerWallet
{
    public static function totals(int $customerId, bool $scoped = true): array
    {
        $sales = Sale::query()->where('customer_id', $customerId)->where('is_draft', false)->where('approval_status', 'approved');
        if ($scoped) $sales = Access::scope($sales, 'sales');
        $ids = (clone $sales)->select('sales.id');
        $billed = (string) $sales->sum('total_rupees');
        $paid = (string) SalePayment::query()->whereIn('sale_id', $ids)
            ->where('approval_status', 'approved')->sum('amount_rupees');
        $balance = SaleMoney::paise($billed) - SaleMoney::paise($paid);

        return ['billed' => $billed, 'paid' => $paid, 'balance' => SaleMoney::decimal(max(0, $balance))];
    }

    public static function refresh(int $customerId): void
    {
        $totals = self::totals($customerId, false);
        DB::table('customers')->where('id', $customerId)->update([
            'total_sales_rupees' => $totals['billed'],
            'total_paid_rupees' => $totals['paid'],
            'balance_rupees' => $totals['balance'],
        ]);
    }
}
