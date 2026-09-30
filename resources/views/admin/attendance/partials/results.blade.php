@forelse($employees as $employee)
    @php
        $staffRecords = $records->get($employee->id, collect());
        $byDay = $staffRecords->keyBy(fn ($record) => $record->work_date->day);
        $stats = $monthly[$employee->id] ?? null;
    @endphp
    <article class="card va-staff-calendar-card" aria-label="{{ $employee->name }} calendar for {{ $start->format('F Y') }}">
        <div class="va-staff-calendar-header"><div class="va-staff-person"><span class="va-staff-avatar">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</span><div><h6>{{ $employee->name }}</h6><small>{{ $employee->mobile }}</small></div></div>
            @if($stats)<div class="va-staff-mini-counts"><span class="is-present">{{ $stats['present'] }} present</span><span class="is-absent">{{ $stats['leave'] }} leave</span>@if($stats['pending'])<span class="is-pending">{{ $stats['pending'] }} pending</span>@endif</div>@endif
        </div>
        <div class="va-staff-weekdays" aria-hidden="true"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
        <div class="va-staff-days">
            @for($blank = 1; $blank < $start->dayOfWeekIso; $blank++)<span class="va-staff-day-empty" aria-hidden="true"></span>@endfor
            @for($day = 1; $day <= $start->daysInMonth; $day++)
                @php
                    $date = $start->copy()->day($day);
                    $record = $byDay->get($day);
                    $status = $record?->approval_status === 'pending' ? 'pending' : ($record?->type === 'leave' ? 'leave' : ($record && $record->minutes > 0 ? 'present' : ($date->isAfter(today()) ? 'future' : 'absent')));
                    $label = match ($status) { 'present' => $record->type === 'full' ? 'Present' : number_format($record->minutes / 60, 1).'h worked', 'leave' => 'Leave', 'pending' => 'Pending', 'future' => 'Upcoming', default => 'Absent' };
                    $canEdit = $record && ! in_array($employee->id, $lockedEmployees) && \App\Support\Access::canEdit('attendance', $record);
                    $canAdd = ! $record && ! in_array($employee->id, $lockedEmployees) && ! $date->isAfter(today()) && \App\Support\Access::allowed('attendance', 'create') && (auth()->user()->role === 'admin' || $date->isToday());
                    $url = $canEdit ? route('attendance.edit', $record) : ($canAdd ? route('attendance.create', ['employee_id' => $employee->id, 'work_date' => $date->toDateString(), 'from_calendar' => 1]) : null);
                @endphp
                @if($url)<a href="{{ $url }}" class="va-staff-day is-{{ $status }}" title="{{ $label }}{{ $record?->note ? ' · '.$record->note : '' }}" aria-label="{{ $date->format('d M Y') }}: {{ $label }}{{ $record?->note ? '. '.$record->note : '' }}. Open attendance">@else<div class="va-staff-day is-{{ $status }}" role="group" title="{{ $label }}{{ $record?->note ? ' · '.$record->note : '' }}" aria-label="{{ $date->format('d M Y') }}: {{ $label }}{{ $record?->note ? '. '.$record->note : '' }}">@endif
                    <span class="va-staff-day-number">{{ $day }}</span><span class="va-staff-day-state">{{ $label }}</span><span class="va-staff-day-dot" aria-hidden="true"></span>
                @if($url)</a>@else</div>@endif
            @endfor
        </div>
        @if($staffRecords->where('type', 'leave')->isNotEmpty())<div class="va-staff-leave-notes"><strong>Leave notes</strong>@foreach($staffRecords->where('type', 'leave')->sortBy('work_date') as $leave)<div><time datetime="{{ $leave->work_date->toDateString() }}">{{ $leave->work_date->format('d M') }}</time><span>{{ $leave->note ?: 'Leave' }}{{ $leave->approval_status === 'pending' ? ' · Pending approval' : '' }}</span></div>@endforeach</div>@endif
        @if($stats)
            @php $balance = $stats['wallet']['balance']; @endphp
            <div class="va-staff-salary-strip">
                <div><small>{{ $start->format('M Y') }} {{ $stats['run'] ? 'salary' : 'earned so far' }}</small><strong>₹{{ \App\Support\SalaryMath::format($stats['earned']) }}</strong></div>
                <div><small>Wallet balance · all months</small><strong class="{{ $balance > 0 ? 'text-danger' : 'text-success' }}">{{ $balance < 0 ? '−' : '' }}₹{{ \App\Support\SalaryMath::format(abs($balance)) }}</strong></div>
                @if(auth()->user()->role === 'admin')<a href="{{ route('salaries.create', ['employee_id' => $employee->id, 'month' => $month]) }}" class="va-staff-wallet-link">Open wallet →</a>@endif
            </div>
        @endif
    </article>
@empty
    <div class="card"><div class="card-body text-center text-muted py-5">No staff found for this month.</div></div>
@endforelse
<div class="mt-3">@include('shared.pagination', ['paginator' => $employees, 'label' => 'Staff calendar pages'])</div>
