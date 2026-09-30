<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class SalaryWallet
{
    // Call while holding the employee row lock in the same transaction as the ledger write.
    public static function refresh(int $employeeId): void
    {
        DB::table('users')->where('id', $employeeId)->update([
            'salary_balance_paise' => SalaryMath::wallet($employeeId)['balance'],
        ]);
    }
}
