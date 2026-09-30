@extends('layouts.admin')
@section('title', 'Mark Leave')
@section('page-title', 'Mark Leave')
@section('content')
<div class="va-staff-workspace">
    <a href="{{ route('attendance.index') }}" class="btn btn-link px-0 mb-2">← Staff calendar</a>
    <section class="card va-staff-panel" aria-labelledby="leaveTitle"><div class="card-body">
        <div class="va-staff-section-heading"><div><span class="va-staff-eyebrow">Time off</span><h5 id="leaveTitle">Mark a leave</h5><p>Choose one staff member or everyone. Existing attendance stays as it is.</p></div><span class="va-staff-section-icon"><i class="ri-calendar-event-line" aria-hidden="true"></i></span></div>
        <form method="POST" action="{{ route('attendance.leave') }}" class="row g-3" data-safe-submit>@csrf
            <div class="col-12 col-md-4"><label for="leaveScope" class="form-label">For *</label><select id="leaveScope" name="scope" class="form-select" required><option value="one" @selected(old('scope') !== 'all')>One staff member</option><option value="all" @selected(old('scope') === 'all')>All approved staff</option></select></div>
            <div class="col-12 col-md-8" id="leaveEmployeeField"><label for="leaveEmployee" class="form-label">Staff member *</label><select id="leaveEmployee" name="employee_id" class="form-select"><option value="">Select staff</option>@foreach($employees as $person)<option value="{{ $person->id }}" @selected((string) old('employee_id', request('employee_id')) === (string) $person->id)>{{ $person->name }} · {{ $person->mobile }}</option>@endforeach</select></div>
            <div class="col-12 col-sm-5"><label for="leaveDate" class="form-label">Leave date *</label><input id="leaveDate" type="date" name="work_date" class="form-control" max="{{ now()->addYear()->toDateString() }}" value="{{ old('work_date', now()->toDateString()) }}" required></div>
            <div class="col-12 col-sm-7"><label for="leaveNote" class="form-label">Note *</label><input id="leaveNote" name="note" class="form-control" maxlength="500" value="{{ old('note') }}" placeholder="Leave reason" required></div>
            <div class="col-12"><button type="submit" class="btn btn-primary" data-submit-button><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span> <span data-submit-label>Mark Leave</span></button></div>
        </form>
        <p class="text-muted small mb-0 mt-3">For everyone, existing entries and salary locked months are skipped.</p>
    </div></section>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/attendance-calendar.css') }}">@endpush
@push('scripts')
<script src="{{ asset('assets/js/record-form.js') }}" defer></script>
<script defer>
document.addEventListener('DOMContentLoaded', () => {
    const scope = document.getElementById('leaveScope');
    const field = document.getElementById('leaveEmployeeField');
    const employee = document.getElementById('leaveEmployee');
    const sync = () => {
        const all = scope.value === 'all';
        field.hidden = all;
        employee.disabled = all;
        employee.required = !all;
    };
    scope.addEventListener('change', sync);
    sync();
});
</script>
@endpush
