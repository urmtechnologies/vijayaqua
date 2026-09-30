@extends('layouts.admin')
@section('title', $attendance ? 'Edit Attendance' : 'Add Attendance')
@section('page-title', $attendance ? 'Edit Attendance' : 'Add Attendance')
@section('page-action')<a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary">Back to Calendar</a>@endsection
@section('content')
<div class="row"><div class="col-xl-7"><div class="card"><div class="card-body">
    <form method="POST" action="{{ $attendance ? route('attendance.update', $attendance) : route('attendance.store') }}" data-safe-submit>@csrf @if($attendance) @method('PUT') @endif
        @if(request('from_calendar'))<input type="hidden" name="from_calendar" value="1">@endif
        <div class="row g-3">
            @if(auth()->user()->role === 'admin')
                <div class="col-md-6"><label for="employee_id" class="form-label">Staff member</label><select id="employee_id" name="employee_id" class="form-select" required>
                    <option value="">Select staff</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(old('employee_id', $attendance?->employee_id ?? request('employee_id')) == $employee->id)>{{ $employee->name }} · {{ $employee->mobile }}</option>@endforeach
                </select>@error('employee_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-md-6"><label for="work_date" class="form-label">Date</label><input id="work_date" name="work_date" type="date" class="form-control" data-today="{{ now()->toDateString() }}" data-leave-max="{{ now()->addYear()->toDateString() }}" max="{{ old('type', $attendance?->type) === 'leave' ? now()->addYear()->toDateString() : now()->toDateString() }}" value="{{ old('work_date', $attendance?->work_date?->format('Y-m-d') ?? request('work_date', now()->toDateString())) }}" required>@error('work_date')<small class="text-danger">{{ $message }}</small>@enderror</div>
            @else
                <div class="col-12"><div class="alert alert-info mb-0">Your attendance for {{ now()->format('d M Y') }}. One entry per day; you can edit it until approved.</div></div>
            @endif
            <div class="col-md-6"><label for="type" class="form-label">Attendance type</label><select id="type" name="type" class="form-select" required>
                <option value="full" @selected(old('type', $attendance?->type ?? 'full') === 'full')>Full day · 12 hours</option>
                <option value="custom" @selected(old('type', $attendance?->type) === 'custom')>Custom hours</option>
                <option value="leave" @selected(old('type', $attendance?->type) === 'leave')>Leave · unpaid</option>
            </select></div>
            <div class="col-md-6" id="hoursGroup"><label for="hours" class="form-label">Hours worked (max 12)</label><input id="hours" name="hours" type="number" class="form-control" step="0.01" min="0.01" max="12" value="{{ old('hours', $attendance?->type === 'custom' ? $attendance->minutes / 60 : '') }}">@error('hours')<small class="text-danger">{{ $message }}</small>@enderror</div>
            <div class="col-12"><label for="note" class="form-label">Note (optional)</label><textarea id="note" name="note" class="form-control" rows="2" maxlength="500">{{ old('note', $attendance?->note) }}</textarea></div>
        </div>
        <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-primary" data-submit-button><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span> <span data-submit-label>{{ $attendance ? 'Save Changes' : 'Save Attendance' }}</span></button><a href="{{ route('attendance.index') }}" class="btn btn-light">Cancel</a></div>
    </form>
    @if($attendance && \App\Support\Access::canDelete('attendance', $attendance))
        <hr><form method="POST" action="{{ route('attendance.destroy', $attendance) }}">@csrf @method('DELETE')<button type="button" class="btn btn-outline-danger" data-va-delete data-va-delete-name="Attendance for {{ $attendance->employee?->name }} on {{ $attendance->work_date->format('d M Y') }}">Delete Attendance</button></form>
    @endif
</div></div></div></div>
@endsection
@push('scripts')<script src="{{ asset('assets/js/record-form.js') }}" defer></script><script src="{{ asset('assets/js/attendance-form.js') }}" defer></script>@endpush
