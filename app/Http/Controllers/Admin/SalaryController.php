<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Attendance, Salary, SalaryAdvance, SalaryPayment, User};
use App\Support\{Access, SalaryMath, SalaryWallet};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalaryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['due', 'settled'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Access::scope(Salary::query(), 'salaries');
        if ($search = trim($filters['search'] ?? '')) {
            $query->whereHas('employee', fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('mobile', 'like', '%'.$search.'%'));
        }
        if ($month = $filters['month'] ?? null) $query->whereDate('month', $month.'-01');
        // The list shows the same overall wallet balance as the staff calendar and wallet page.
        $balance = '(SELECT COALESCE(SUM(s2.earned_paise),0) FROM salaries s2 WHERE s2.employee_id=salaries.employee_id)'
            .' - (SELECT COALESCE(SUM(CASE WHEN a.entry_type = \'debit\' THEN -CAST(a.amount_paise AS SIGNED) ELSE CAST(a.amount_paise AS SIGNED) END),0) FROM salary_advances a WHERE a.employee_id=salaries.employee_id)'
            .' - (SELECT COALESCE(SUM(p.amount_paise),0) FROM salary_payments p JOIN salaries s3 ON s3.id=p.salary_id WHERE s3.employee_id=salaries.employee_id)';
        if (($filters['status'] ?? null) === 'due') $query->whereRaw($balance.' > 0');
        if (($filters['status'] ?? null) === 'settled') $query->whereRaw($balance.' <= 0');

        $salaries = $query->with(['employee', 'creator'])->orderByDesc('month')->orderByDesc('id')->paginate(10)->withQueryString();
        $wallets = SalaryMath::wallets($salaries->pluck('employee_id'));

        return $request->ajax()
            ? view('admin.salaries.partials.results', compact('salaries', 'wallets'))
            : view('admin.salaries.index', compact('salaries', 'wallets'));
    }

    public function create(Request $request): View
    {
        $this->admin();
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', '!=', 'admin')->where('approval_status', 'approved'))],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $staffOptions = User::where('role', '!=', 'admin')->where('approval_status', 'approved')
            ->orderBy('name')->get(['id', 'name', 'mobile', 'salary', 'salary_balance_paise']);
        $month = $filters['month'] ?? now()->startOfMonth()->subMonth()->format('Y-m');
        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $selectedEmployee = isset($filters['employee_id'])
            ? $staffOptions->firstWhere('id', (int) $filters['employee_id']) : $staffOptions->first();
        $wallet = null;
        $history = collect();
        $monthly = [];
        if ($selectedEmployee) {
            $id = $selectedEmployee->id;
            $wallet = SalaryMath::wallet($id);
            $calculation = $this->calculation($id, $month);
            $monthly[$id] = [
                'run' => $calculation['existing'], 'rate' => $calculation['monthlyPaise'],
                'earned' => $calculation['earned'], 'pending' => $calculation['pendingCount'],
            ];
            $advances = SalaryAdvance::where('employee_id', $id)->with('creator')->get();
            $payments = SalaryPayment::whereHas('salary', fn ($q) => $q->where('employee_id', $id))
                ->with(['creator', 'salary'])->get();
            $history = $advances->map(fn ($entry) => [
                'date' => $entry->paid_on, 'type' => $entry->entry_type, 'amount' => $entry->amount_paise,
                'method' => $entry->method, 'note' => $entry->note, 'creator' => $entry->creator?->name,
                'month' => $entry->month->format('M Y'), 'id' => $entry->id,
            ])->concat($payments->map(fn ($entry) => [
                'date' => $entry->paid_on, 'type' => 'credit', 'amount' => $entry->amount_paise,
                'method' => $entry->method, 'note' => $entry->note, 'creator' => $entry->creator?->name,
                'month' => $entry->salary->month->format('M Y'), 'id' => $entry->id,
            ]))->sortByDesc(fn ($entry) => $entry['date']->format('Y-m-d').' '.str_pad((string) $entry['id'], 12, '0', STR_PAD_LEFT))->values();
        }

        return view('admin.salaries.create', compact('staffOptions', 'selectedEmployee', 'wallet', 'history', 'monthly', 'month', 'start'));
    }

    public function preview(Request $request): View
    {
        $this->admin();
        $data = $this->selection($request);
        return view('admin.salaries.partials.preview', $this->calculation($data['employee_id'], $data['month']));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->admin();
        $data = $this->selection($request);
        $salary = DB::transaction(function () use ($data): Salary {
            User::whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $calculation = $this->calculation($data['employee_id'], $data['month']);
            if ($calculation['existing']) {
                throw ValidationException::withMessages(['month' => 'Salary for this person and month is already generated.']);
            }
            if ($calculation['pendingCount']) {
                throw ValidationException::withMessages(['month' => 'Review pending attendance before generating this salary.']);
            }
            $salary = Salary::create([
                'employee_id' => $data['employee_id'], 'month' => $data['month'].'-01',
                'monthly_salary_paise' => $calculation['monthlyPaise'],
                'earned_paise' => $calculation['earned'],
            ]);
            SalaryWallet::refresh($data['employee_id']);
            return $salary;
        }, 3);

        return redirect()->route('salaries.create', ['month' => $data['month'], 'employee_id' => $salary->employee_id])
            ->with('success', 'Monthly salary generated. Attendance for this month is locked.');
    }

    public function show(Salary $salary): View
    {
        $salary->load(['employee', 'creator', 'payments.creator']);
        $month = $salary->month->format('Y-m');
        $calculation = $this->calculation($salary->employee_id, $month);
        $advances = SalaryAdvance::where('employee_id', $salary->employee_id)->whereDate('month', $salary->month)
            ->with('creator')->orderBy('paid_on')->get();

        return view('admin.salaries.show', compact('salary', 'calculation', 'advances'));
    }

    public function advance(Request $request): RedirectResponse
    {
        $this->admin();
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', '!=', 'admin')->where('approval_status', 'approved'))],
            'month' => ['required', 'date_format:Y-m'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount_rupees' => ['required', 'regex:/^(?:(?:[1-9][0-9]{0,10})(?:\.[0-9]{1,2})?|0\.(?:0[1-9]|[1-9][0-9]?))$/'],
            'entry_type' => ['nullable', Rule::in(['credit', 'debit'])],
            'method' => ['required', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($data): void {
            User::whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $type = $data['entry_type'] ?? 'credit';
            $amount = SalaryMath::paise($data['amount_rupees']);
            if ($type === 'debit') {
                $entries = SalaryAdvance::where('employee_id', $data['employee_id']);
                $paid = (int) (clone $entries)->where('entry_type', 'credit')->sum('amount_paise')
                    + (int) \App\Models\SalaryPayment::join('salaries', 'salaries.id', '=', 'salary_payments.salary_id')
                        ->where('salaries.employee_id', $data['employee_id'])->sum('salary_payments.amount_paise');
                $returned = (int) (clone $entries)->where('entry_type', 'debit')->sum('amount_paise');
                if ($amount > $paid - $returned) {
                    throw ValidationException::withMessages(['amount_rupees' => 'Returned amount cannot exceed the amount already paid to this staff member.']);
                }
            }
            SalaryAdvance::create([
                'employee_id' => $data['employee_id'], 'month' => $data['month'].'-01',
                'paid_on' => $data['paid_on'], 'amount_paise' => $amount, 'entry_type' => $type,
                'method' => $data['method'], 'note' => $data['note'] ?? null,
            ]);
            SalaryWallet::refresh($data['employee_id']);
        }, 3);

        return back()->with('success', ($data['entry_type'] ?? 'credit') === 'debit' ? 'Returned payment recorded.' : 'Salary payment recorded.');
    }

    public function payment(Request $request, Salary $salary): RedirectResponse
    {
        $this->admin();
        $data = $request->validate([
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount_rupees' => ['required', 'regex:/^(?:(?:[1-9][0-9]{0,10})(?:\.[0-9]{1,2})?|0\.(?:0[1-9]|[1-9][0-9]?))$/'],
            'method' => ['required', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($salary, $data): void {
            User::withTrashed()->whereKey($salary->employee_id)->lockForUpdate()->firstOrFail();
            $run = Salary::whereKey($salary->id)->lockForUpdate()->firstOrFail();
            $amount = SalaryMath::paise($data['amount_rupees']);
            $due = SalaryMath::before($run->employee_id, $run->month->format('Y-m-d')) + SalaryMath::balance($run);
            if ($amount > $due) {
                throw ValidationException::withMessages(['amount_rupees' => 'Payment exceeds the outstanding balance through this month.']);
            }
            $remaining = $amount;
            foreach (Salary::where('employee_id', $run->employee_id)->whereDate('month', '<=', $run->month)->orderBy('month')->get() as $period) {
                $open = max(0, SalaryMath::balance($period));
                $allocated = min($remaining, $open);
                if ($allocated) $period->payments()->create([
                    'paid_on' => $data['paid_on'], 'amount_paise' => $allocated,
                    'method' => $data['method'], 'note' => $data['note'] ?? null,
                ]);
                $remaining -= $allocated;
                if (! $remaining) break;
            }
            if ($remaining) throw ValidationException::withMessages(['amount_rupees' => 'No open salary amount remains for this payment.']);
            SalaryWallet::refresh($run->employee_id);
        }, 3);

        return redirect()->route('salaries.show', $salary)->with('success', 'Salary payment recorded. Older unpaid months were covered first.');
    }

    private function selection(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', '!=', 'admin')->where('approval_status', 'approved'))],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        if ($data['month'] >= now()->format('Y-m')) {
            throw ValidationException::withMessages(['month' => 'Select a completed month. The previous month is selected by default.']);
        }
        return $data;
    }

    private function calculation(int $employeeId, string $month): array
    {
        $employee = User::withTrashed()->findOrFail($employeeId);
        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $existing = Salary::where('employee_id', $employeeId)->whereDate('month', $start->toDateString())->first();
        $monthlyPaise = $existing ? (int) $existing->monthly_salary_paise : SalaryMath::paise((string) $employee->salary);
        $records = Attendance::where('employee_id', $employeeId)
            ->where('work_date', '>=', $start->toDateString())->where('work_date', '<', $start->copy()->addMonth()->toDateString())
            ->with(['creator', 'approver'])->get()->keyBy(fn ($record) => $record->work_date->toDateString());
        $days = collect();
        for ($date = $start->copy(); $date->month === $start->month; $date->addDay()) {
            $record = $records->get($date->toDateString());
            $minutes = $record?->approval_status === 'approved' ? $record->minutes : 0;
            $days->push(['date' => $date->copy(), 'record' => $record,
                'earned' => SalaryMath::earned($monthlyPaise, $minutes, $date)]);
        }
        $earned = $existing ? (int) $existing->earned_paise : $days->sum('earned');
        $entries = SalaryAdvance::where('employee_id', $employeeId)->whereDate('month', $start->toDateString());
        $advance = (int) (clone $entries)->where('entry_type', 'credit')->sum('amount_paise')
            - (int) (clone $entries)->where('entry_type', 'debit')->sum('amount_paise');
        $carry = SalaryMath::before($employeeId, $start->toDateString());
        $paid = $existing ? (int) $existing->payments()->sum('amount_paise') : 0;
        return compact('employee', 'start', 'existing', 'monthlyPaise', 'days', 'earned', 'advance', 'carry', 'paid') + [
            'pendingCount' => $records->where('approval_status', 'pending')->count(),
            'due' => $earned + $carry - $advance - $paid,
        ];
    }

    private function admin(): void
    {
        abort_unless(auth()->user()->role === 'admin', 403);
    }
}
