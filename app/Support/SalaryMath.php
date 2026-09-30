<?php

namespace App\Support;

use App\Models\Salary;
use App\Models\SalaryAdvance;
use App\Models\SalaryPayment;
use Illuminate\Support\Carbon;

final class SalaryMath
{
    public static function paise(string $decimal): int
    {
        [$rupees, $paise] = array_pad(explode('.', $decimal, 2), 2, '');
        return (int) $rupees * 100 + (int) str_pad($paise, 2, '0');
    }

    public static function format(int $paise): string
    {
        return ($paise < 0 ? '-' : '').number_format(abs($paise) / 100, 2);
    }

    public static function earned(int $monthlyPaise, int $minutes, Carbon $date): int
    {
        $denominator = $date->daysInMonth * 720;
        return intdiv($monthlyPaise * $minutes + intdiv($denominator, 2), $denominator);
    }

    public static function balance(Salary $salary): int
    {
        $entries = SalaryAdvance::where('employee_id', $salary->employee_id)->whereDate('month', $salary->month);
        return (int) $salary->earned_paise
            - (int) (clone $entries)->where('entry_type', 'credit')->sum('amount_paise')
            + (int) (clone $entries)->where('entry_type', 'debit')->sum('amount_paise')
            - (int) $salary->payments()->sum('amount_paise');
    }

    public static function before(int $employeeId, string $month): int
    {
        $runs = Salary::where('employee_id', $employeeId)->whereDate('month', '<', $month);
        $entries = SalaryAdvance::where('employee_id', $employeeId)->whereDate('month', '<', $month);
        return (int) (clone $runs)->sum('earned_paise')
            - (int) (clone $entries)->where('entry_type', 'credit')->sum('amount_paise')
            + (int) (clone $entries)->where('entry_type', 'debit')->sum('amount_paise')
            - (int) SalaryPayment::whereIn('salary_id', (clone $runs)->select('id'))->sum('amount_paise');
    }

    public static function wallet(int $employeeId): array
    {
        $entries = SalaryAdvance::where('employee_id', $employeeId);
        $earned = (int) Salary::where('employee_id', $employeeId)->sum('earned_paise');
        $credits = (int) (clone $entries)->where('entry_type', 'credit')->sum('amount_paise');
        $returns = (int) (clone $entries)->where('entry_type', 'debit')->sum('amount_paise');
        $salaryPayments = (int) SalaryPayment::whereIn('salary_id', Salary::where('employee_id', $employeeId)->select('id'))->sum('amount_paise');

        return [
            'earned' => $earned,
            'paid' => $credits + $salaryPayments,
            'returned' => $returns,
            'balance' => $earned - $credits - $salaryPayments + $returns,
        ];
    }
}
