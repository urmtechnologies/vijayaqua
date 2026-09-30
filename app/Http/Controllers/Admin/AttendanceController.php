<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Attendance, Salary, SalaryAdvance, SalaryPayment, User};
use App\Support\{Access, SalaryMath};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'], 'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['full', 'custom', 'leave'])],
            'approval' => ['nullable', Rule::in(['pending', 'approved'])],
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $month = $filters['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $query = User::query()->where('role', '!=', 'admin')->where('approval_status', 'approved');
        if (! Access::all('attendance')) $query->whereKey(auth()->id());
        if ($search = trim($filters['search'] ?? '')) $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('mobile', 'like', '%'.$search.'%'));
        if (! empty($filters['type']) || ! empty($filters['approval'])) {
            $query->whereHas('attendances', function ($q) use ($filters, $start): void {
                $q->where('work_date', '>=', $start->toDateString())->where('work_date', '<', $start->copy()->addMonth()->toDateString());
                if (! empty($filters['type'])) $q->where('type', $filters['type']);
                if (! empty($filters['approval'])) $q->where('approval_status', $filters['approval']);
            });
        }
        $employees = $query->orderBy('name')->paginate(10)->withQueryString();
        $records = Attendance::whereIn('employee_id', $employees->pluck('id'))
            ->where('work_date', '>=', $start->toDateString())->where('work_date', '<', $start->copy()->addMonth()->toDateString())
            ->with(['creator', 'approver'])->get()->groupBy('employee_id');
        $lockedEmployees = Salary::whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('month', $start->toDateString())->pluck('employee_id')->all();
        $showSalary = Access::allowed('salaries');
        $staffOptions = $request->user()->role === 'admin'
            ? User::where('role', '!=', 'admin')->where('approval_status', 'approved')->orderBy('name')->get(['id', 'name', 'mobile', 'salary'])
            : collect();
        $selectedEmployee = null;
        $wallet = null;
        $history = collect();
        $monthly = [];
        if ($showSalary) {
            $selectedId = (int) ($filters['employee_id'] ?? (Access::all('salaries') ? ($employees->first()?->id ?? 0) : $request->user()->id));
            if ($selectedId) {
                $selectedEmployee = User::where('role', '!=', 'admin')->where('approval_status', 'approved')->findOrFail($selectedId);
                abort_unless((Access::all('attendance') && Access::all('salaries')) || $selectedId === $request->user()->id, 403);
                $wallet = SalaryMath::wallet($selectedId);
                $advances = SalaryAdvance::where('employee_id', $selectedId)->with('creator')->get();
                $payments = SalaryPayment::whereHas('salary', fn ($q) => $q->where('employee_id', $selectedId))
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

            $salaryStaff = collect($employees->items())->filter(fn ($person) => Access::all('salaries') || $person->id === $request->user()->id);
            $ids = $salaryStaff->pluck('id')->push($selectedEmployee?->id)->filter()->unique();
            $runs = Salary::whereIn('employee_id', $ids)->whereDate('month', $start->toDateString())->with('creator')->get()->keyBy('employee_id');
            $monthEntries = SalaryAdvance::whereIn('employee_id', $ids)->whereDate('month', $start->toDateString())->get()->groupBy('employee_id');
            $runPayments = SalaryPayment::whereIn('salary_id', $runs->pluck('id'))->selectRaw('salary_id, SUM(amount_paise) AS total')
                ->groupBy('salary_id')->pluck('total', 'salary_id');
            foreach ($salaryStaff->concat($selectedEmployee && ! $salaryStaff->contains('id', $selectedEmployee->id) ? [$selectedEmployee] : []) as $employee) {
                $items = $records->get($employee->id, collect());
                if (! $employees->getCollection()->contains('id', $employee->id)) {
                    $items = Attendance::where('employee_id', $employee->id)
                        ->where('work_date', '>=', $start->toDateString())->where('work_date', '<', $start->copy()->addMonth()->toDateString())->get();
                }
                $run = $runs->get($employee->id);
                $rate = $run ? (int) $run->monthly_salary_paise : SalaryMath::paise((string) $employee->salary);
                $earned = $run ? (int) $run->earned_paise : (int) $items->sum(fn ($record) => $record->approval_status === 'approved'
                    ? SalaryMath::earned($rate, $record->minutes, $record->work_date) : 0);
                $entries = $monthEntries->get($employee->id, collect());
                $paid = (int) $entries->where('entry_type', 'credit')->sum('amount_paise')
                    - (int) $entries->where('entry_type', 'debit')->sum('amount_paise')
                    + (int) ($run ? $runPayments->get($run->id, 0) : 0);
                $monthly[$employee->id] = [
                    'run' => $run, 'rate' => $rate, 'earned' => $earned, 'paid' => $paid, 'due' => $earned - $paid,
                    'present' => $items->filter(fn ($record) => $record->approval_status === 'approved' && $record->minutes > 0)->count(),
                    'leave' => $items->filter(fn ($record) => $record->approval_status === 'approved' && $record->type === 'leave')->count(),
                    'pending' => $items->where('approval_status', 'pending')->count(),
                ];
            }
        }

        return view('admin.attendance.index', compact('employees', 'records', 'start', 'month', 'lockedEmployees',
            'staffOptions', 'showSalary', 'selectedEmployee', 'wallet', 'history', 'monthly'));
    }

    public function markLeave(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', '!=', 'admin')->where('approval_status', 'approved'))],
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->addYear()->toDateString()],
            'note' => ['required', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($data): void {
            User::whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $this->ensurePeriodOpen($data);
            if (Attendance::where('employee_id', $data['employee_id'])->whereDate('work_date', $data['work_date'])->exists()) {
                throw ValidationException::withMessages(['work_date' => 'This date already has an attendance entry. Open the date in the calendar to edit it.']);
            }
            Attendance::create($data + ['type' => 'leave', 'minutes' => 0]);
        }, 3);

        return redirect()->route('attendance.index', ['month' => substr($data['work_date'], 0, 7), 'employee_id' => $data['employee_id']])
            ->with('success', 'Leave marked on the staff calendar.');
    }

    public function create(): View
    {
        return view('admin.attendance.form', [
            'attendance' => null,
            'employees' => User::where('role', '!=', 'admin')->where('approval_status', 'approved')->orderBy('name')->get(['id', 'name', 'mobile']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data): void {
            User::whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $this->ensurePeriodOpen($data);
            if (Attendance::where('employee_id', $data['employee_id'])->whereDate('work_date', $data['work_date'])->exists()) {
                throw ValidationException::withMessages(['work_date' => 'Attendance already exists for this person and date.']);
            }
            Attendance::create($data);
        }, 3);

        $target = $request->input('from_calendar') === '1'
            ? ['month' => substr($data['work_date'], 0, 7), 'employee_id' => $data['employee_id']]
            : [];
        return redirect()->route('attendance.index', $target)->with('success', 'Attendance saved. Staff entries need admin approval.');
    }

    public function edit(Attendance $attendance): View
    {
        $this->ensurePeriodOpen(['employee_id' => $attendance->employee_id, 'work_date' => $attendance->work_date->format('Y-m-d')]);
        return view('admin.attendance.form', [
            'attendance' => $attendance,
            'employees' => User::where('role', '!=', 'admin')->where('approval_status', 'approved')->orderBy('name')->get(['id', 'name', 'mobile']),
        ]);
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        $data = $this->validated($request, $attendance);
        DB::transaction(function () use ($attendance, $data): void {
            User::withTrashed()->whereKey($attendance->employee_id)->lockForUpdate()->firstOrFail();
            $record = Attendance::whereKey($attendance->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canEdit('attendance', $record), 403);
            $this->ensurePeriodOpen(['employee_id' => $record->employee_id, 'work_date' => $record->work_date->format('Y-m-d')]);
            $this->ensurePeriodOpen($data);
            if (Attendance::where('employee_id', $data['employee_id'])->whereDate('work_date', $data['work_date'])->where('id', '!=', $record->id)->exists()) {
                throw ValidationException::withMessages(['work_date' => 'Attendance already exists for this person and date.']);
            }
            $record->update($data);
        }, 3);

        return redirect()->route('attendance.index', ['month' => substr($data['work_date'], 0, 7)])->with('success', 'Attendance updated.');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        DB::transaction(function () use ($attendance): void {
            User::withTrashed()->whereKey($attendance->employee_id)->lockForUpdate()->firstOrFail();
            $record = Attendance::whereKey($attendance->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canDelete('attendance', $record), 403);
            $this->ensurePeriodOpen(['employee_id' => $record->employee_id, 'work_date' => $record->work_date->format('Y-m-d')]);
            $record->delete();
        }, 3);

        return redirect()->route('attendance.index', ['month' => $attendance->work_date->format('Y-m'), 'employee_id' => $attendance->employee_id])
            ->with('success', 'Attendance removed.');
    }

    private function validated(Request $request, ?Attendance $attendance = null): array
    {
        $admin = $request->user()->role === 'admin';
        $data = $request->validate([
            'employee_id' => [$admin ? 'required' : 'nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', '!=', 'admin')->where('approval_status', 'approved'))],
            'work_date' => [$admin ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.($admin && $request->input('type') === 'leave' ? now()->addYear()->toDateString() : now()->toDateString())],
            'type' => ['required', Rule::in(['full', 'custom', 'leave'])],
            'hours' => ['nullable', 'required_if:type,custom', 'numeric', 'gt:0', 'lte:12'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        if (! $admin) {
            // Never trust staff-submitted employee or date fields.
            $data['employee_id'] = $request->user()->id;
            $data['work_date'] = now()->toDateString();
            if ($attendance && ($attendance->employee_id !== $request->user()->id || $attendance->work_date->toDateString() !== $data['work_date'])) abort(403);
        }
        $data['minutes'] = match ($data['type']) {
            'full' => 720, 'leave' => 0,
            default => (int) round((float) $data['hours'] * 60),
        };
        unset($data['hours']);

        return $data;
    }

    private function ensurePeriodOpen(array $data): void
    {
        if (Salary::where('employee_id', $data['employee_id'])->whereDate('month', substr($data['work_date'], 0, 7).'-01')->exists()) {
            throw ValidationException::withMessages(['work_date' => 'Salary is already generated for this month. Attendance is locked.']);
        }
    }
}
