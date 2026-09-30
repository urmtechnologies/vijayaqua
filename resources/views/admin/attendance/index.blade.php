@extends('layouts.admin')
@section('title', 'Staff Calendar & Salary')
@section('page-title', 'Attendance & Salary')
@section('page-action')@include('shared.report-exports', ['reportModule' => 'attendance'])@endsection
@section('content')
<div class="va-staff-workspace">
    @if(auth()->user()->role === 'admin')
        <section class="card va-staff-panel" aria-labelledby="leaveTitle"><div class="card-body">
            <div class="va-staff-section-heading"><div><span class="va-staff-eyebrow">Time off</span><h5 id="leaveTitle">Mark leave</h5><p>Choose a staff member, date and note. The leave appears on their calendar.</p></div><span class="va-staff-section-icon"><i class="ri-calendar-event-line" aria-hidden="true"></i></span></div>
            <form method="POST" action="{{ route('attendance.leave') }}" class="row g-2 align-items-end">@csrf
                <div class="col-12 col-md-4"><label class="form-label" for="leaveEmployee">Staff *</label><select id="leaveEmployee" name="employee_id" class="form-select" required><option value="">Select staff</option>@foreach($staffOptions as $person)<option value="{{ $person->id }}" @selected((string) old('employee_id', $selectedEmployee?->id) === (string) $person->id)>{{ $person->name }} · {{ $person->mobile }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><label class="form-label" for="leaveDate">Date *</label><input id="leaveDate" name="work_date" type="date" class="form-control" max="{{ now()->addYear()->toDateString() }}" value="{{ old('work_date', now()->toDateString()) }}" required></div>
                <div class="col-6 col-md-4"><label class="form-label" for="leaveNote">Leave note *</label><input id="leaveNote" name="note" class="form-control" maxlength="500" value="{{ old('note') }}" placeholder="Reason or short note" required></div>
                <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Mark leave</button></div>
            </form>
        </div></section>
    @endif

    @if($showSalary)@include('admin.attendance.partials.wallet')@endif

    <section class="va-staff-calendar-area" aria-labelledby="calendarTitle">
        <div class="va-staff-calendar-top"><div><span class="va-staff-eyebrow">Monthly overview</span><h5 id="calendarTitle">Staff calendars</h5></div>
            @if(\App\Support\Access::allowed('attendance', 'create'))<a href="{{ route('attendance.create') }}" class="btn btn-outline-primary btn-sm">+ Mark attendance</a>@endif
        </div>
        <form method="GET" action="{{ route('attendance.index') }}" class="va-staff-month-controls">
            @if($selectedEmployee)<input type="hidden" name="employee_id" value="{{ $selectedEmployee->id }}">@endif
            <a href="{{ route('attendance.index', array_merge(request()->except('month', 'page'), ['month' => $start->copy()->subMonth()->format('Y-m')])) }}" class="va-staff-month-arrow" aria-label="Previous month">‹</a>
            <div class="va-staff-month-title"><strong>{{ $start->format('F Y') }}</strong><small>{{ $employees->total() }} staff</small></div>
            <a href="{{ route('attendance.index', array_merge(request()->except('month', 'page'), ['month' => $start->copy()->addMonth()->format('Y-m')])) }}" class="va-staff-month-arrow" aria-label="Next month">›</a>
            <input type="month" name="month" value="{{ $month }}" class="form-control va-staff-month-input" aria-label="Choose month">
            <button type="submit" class="btn btn-outline-primary btn-sm">Go</button>
        </form>
        <form method="GET" action="{{ route('attendance.index') }}" class="va-staff-filter-form row g-2 align-items-end">
            <input type="hidden" name="month" value="{{ $month }}">@if($selectedEmployee)<input type="hidden" name="employee_id" value="{{ $selectedEmployee->id }}">@endif
            <div class="col-12 col-sm-6 col-md-4"><label class="form-label" for="calendarSearch">Staff name or mobile</label><input id="calendarSearch" name="search" class="form-control" type="search" placeholder="Search staff" value="{{ request('search') }}"></div>
            <div class="col-6 col-sm-3 col-md-3"><label class="form-label" for="calendarType">Entry type</label><select id="calendarType" name="type" class="form-select"><option value="">All types</option>@foreach(['full' => 'Full day', 'custom' => 'Custom hours', 'leave' => 'Leave'] as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-6 col-sm-3 col-md-3"><label class="form-label" for="calendarApproval">Approval</label><select id="calendarApproval" name="approval" class="form-select"><option value="">All</option><option value="approved" @selected(request('approval') === 'approved')>Approved</option><option value="pending" @selected(request('approval') === 'pending')>Pending</option></select></div>
            <div class="col-12 col-md-2 d-flex gap-2"><button type="submit" class="btn btn-primary flex-grow-1">Apply</button><a href="{{ route('attendance.index', ['month' => $month]) }}" class="btn btn-light">Clear</a></div>
        </form>
        <div class="va-staff-legend"><span><i class="is-present"></i> Present</span><span><i class="is-absent"></i> Absent / leave</span><span><i class="is-pending"></i> Pending</span><span><i class="is-future"></i> Upcoming</span></div>
        @include('admin.attendance.partials.results')
    </section>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/attendance-calendar.css') }}">@endpush
