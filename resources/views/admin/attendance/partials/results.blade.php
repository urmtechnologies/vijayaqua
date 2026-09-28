<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" data-ledger-nav href="{{ route('attendance.index', array_merge(request()->except('month', 'page'), ['month' => $start->copy()->subMonth()->format('Y-m')])) }}" aria-label="Previous month">‹</a>
        <strong>{{ $start->format('F Y') }}</strong>
        <a class="btn btn-outline-secondary btn-sm" data-ledger-nav href="{{ route('attendance.index', array_merge(request()->except('month', 'page'), ['month' => $start->copy()->addMonth()->format('Y-m')])) }}" aria-label="Next month">›</a>
    </div>
    <span class="text-muted">{{ $employees->total() }} staff</span>
</div>
<div class="table-responsive va-calendar-scroll"><table class="table table-bordered table-sm text-center align-middle va-calendar">
    <thead class="table-light"><tr><th class="va-calendar-name text-start">Staff</th>@for($day = 1; $day <= $start->daysInMonth; $day++)<th>{{ str_pad($day, 2, '0', STR_PAD_LEFT) }}</th>@endfor</tr></thead>
    <tbody>
    @forelse($employees as $employee)
        @php $byDay = ($records->get($employee->id) ?? collect())->keyBy(fn ($record) => $record->work_date->day); @endphp
        <tr><th class="va-calendar-name text-start">{{ $employee->name }}<small class="d-block text-muted">{{ $employee->mobile }}</small></th>
        @for($day = 1; $day <= $start->daysInMonth; $day++)
            @php $record = $byDay->get($day); @endphp
            <td class="{{ $record?->approval_status === 'pending' ? 'va-calendar-pending' : '' }}" title="{{ $record ? ucfirst($record->type).' · '.$record->minutes.' minutes · Added by '.($record->creator?->name ?? 'System').' · '.ucfirst($record->approval_status) : 'No attendance' }}">
                @if($record)
                    @if(!in_array($employee->id, $lockedEmployees) && \App\Support\Access::canEdit('attendance', $record))
                        <a href="{{ route('attendance.edit', $record) }}" aria-label="Edit {{ $employee->name }} attendance on day {{ $day }}">{{ $record->type === 'full' ? 'F' : ($record->type === 'leave' ? 'L' : number_format($record->minutes / 60, 1)) }}</a>
                    @else
                        {{ $record->type === 'full' ? 'F' : ($record->type === 'leave' ? 'L' : number_format($record->minutes / 60, 1)) }}
                    @endif
                @else · @endif
            </td>
        @endfor</tr>
    @empty
        <tr><td colspan="{{ $start->daysInMonth + 1 }}" class="py-5 text-muted">No staff found.</td></tr>
    @endforelse
    </tbody>
</table></div>
@include('shared.pagination', ['paginator' => $employees, 'label' => 'Attendance staff pages'])
