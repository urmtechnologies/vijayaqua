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
        return self::wallets([$employeeId])[$employeeId];
    }

    public static function wallets(iterable $employeeIds): array
    {
        $ids = collect($employeeIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (! $ids) return [];

        $earned = Salary::whereIn('employee_id', $ids)
            ->selectRaw('employee_id, SUM(earned_paise) AS amount')->groupBy('employee_id')
            ->pluck('amount', 'employee_id');
        $entries = SalaryAdvance::whereIn('employee_id', $ids)
            ->selectRaw("employee_id, SUM(CASE WHEN entry_type = 'credit' THEN amount_paise ELSE 0 END) AS credits, SUM(CASE WHEN entry_type = 'debit' THEN amount_paise ELSE 0 END) AS returns")
            ->groupBy('employee_id')->get()->keyBy('employee_id');
        $payments = SalaryPayment::join('salaries', 'salaries.id', '=', 'salary_payments.salary_id')
            ->whereIn('salaries.employee_id', $ids)
            ->selectRaw('salaries.employee_id AS employee_id, SUM(salary_payments.amount_paise) AS amount')
            ->groupBy('salaries.employee_id')->pluck('amount', 'employee_id');

        $wallets = [];
        foreach ($ids as $id) {
            $salary = (int) ($earned[$id] ?? 0);
            $entry = $entries->get($id);
            $paid = (int) ($entry?->credits ?? 0) + (int) ($payments[$id] ?? 0);
            $returned = (int) ($entry?->returns ?? 0);
            $wallets[$id] = [
                'earned' => $salary, 'paid' => $paid, 'returned' => $returned,
                'balance' => $salary - $paid + $returned,
            ];
        }

        return $wallets;
    }
}
