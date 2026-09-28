@extends('layouts.admin')
@section('title', 'Generate Salary')
@section('page-title', 'Generate Salary')
@section('page-action')<a href="{{ route('salaries.index') }}" class="btn btn-outline-secondary">Salary List</a>@endsection
@section('content')
<div class="card"><div class="card-body">
    <p class="text-muted">Choose a completed month. A full day is 12 hours. Daily earnings use monthly salary divided by the calendar days in that month; custom hours are proportional. Leave, missing days, and pending attendance earn ₹0. Review pending attendance before generation.</p>
    <div class="row g-3 align-items-end" id="salarySelection" data-preview-url="{{ route('salaries.preview') }}">
        <div class="col-md-5"><label for="employee_id" class="form-label">Staff member</label><select id="employee_id" class="form-select" required><option value="">Select staff</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->name }} · {{ $employee->mobile }}</option>@endforeach</select></div>
        <div class="col-md-3"><label for="month" class="form-label">Salary month</label><input id="month" type="month" class="form-control" max="{{ $defaultMonth }}" value="{{ old('month', $defaultMonth) }}"></div>
        <div class="col-md-4"><button type="button" id="fetchSalary" class="btn btn-outline-primary"><span id="fetchSpinner" class="spinner-border spinner-border-sm d-none"></span> <span id="fetchLabel">Fetch Attendance</span></button></div>
    </div>
    @if($errors->any())<div class="alert alert-danger mt-3" role="alert">{{ $errors->first() }}</div>@endif
    <div id="salaryPreview" class="mt-4" aria-live="polite"></div>
    <form id="generateSalary" method="POST" action="{{ route('salaries.store') }}" class="mt-4 d-none" data-safe-submit>@csrf
        <input type="hidden" name="employee_id"><input type="hidden" name="month">
        <button type="submit" class="btn btn-primary" data-submit-button><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span> <span data-submit-label>Generate Salary</span></button>
    </form>
</div></div>
<div class="card"><div class="card-header"><h4 class="card-title mb-0">Record an advance</h4></div><div class="card-body">
    <p class="text-muted small">Advances are assigned to a month and reduce that month’s salary. Any extra credit carries to later months.</p>
    <form method="POST" action="{{ route('salaries.advance') }}" data-safe-submit>@csrf
        <div class="row g-3"><div class="col-md-4"><label class="form-label">Staff</label><select name="employee_id" class="form-select" required><option value="">Select staff</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Month to adjust</label><input type="month" name="month" value="{{ now()->format('Y-m') }}" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Paid on</label><input type="date" name="paid_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Amount (₹)</label><input type="number" name="amount_rupees" min="0.01" step="0.01" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Method</label><select name="method" class="form-select"><option value="cash">Cash</option><option value="upi">UPI</option><option value="bank">Bank</option><option value="other">Other</option></select></div>
            <div class="col-md-10"><label class="form-label">Note</label><input name="note" maxlength="500" class="form-control" placeholder="Optional"></div>
            <div class="col-md-2 align-self-end"><button class="btn btn-outline-primary w-100" data-submit-button><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span> <span data-submit-label>Save Advance</span></button></div>
        </div>
    </form>
</div></div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/people.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/record-form.js') }}" defer></script><script src="{{ asset('assets/js/salary-preview.js') }}" defer></script>@endpush
