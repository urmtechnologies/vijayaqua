@extends('layouts.admin')
@section('title', 'Staff Calendar')
@section('page-title', 'Staff Calendar')
@section('page-action')@include('shared.report-exports', ['reportModule' => 'attendance'])@endsection
@section('content')
<div class="va-staff-workspace">
    @if(auth()->user()->role === 'admin')
        <nav class="va-staff-page-actions" aria-label="Staff management">
            <a href="{{ route('attendance.leave.create') }}" class="btn btn-outline-primary"><i class="ri-calendar-event-line" aria-hidden="true"></i> Mark Leave</a>
            <a href="{{ route('salaries.create') }}" class="btn btn-primary"><i class="ri-wallet-3-line" aria-hidden="true"></i> Add Salary</a>
        </nav>
    @endif

    <section class="va-staff-calendar-area" aria-labelledby="calendarTitle">
        <div class="va-staff-calendar-top"><div><span class="va-staff-eyebrow">Monthly overview</span><h5 id="calendarTitle">Staff calendars</h5></div>
            @if(\App\Support\Access::allowed('attendance', 'create'))<a href="{{ route('attendance.create') }}" class="btn btn-outline-primary btn-sm">+ Mark attendance</a>@endif
        </div>
        <form method="GET" action="{{ route('attendance.index') }}" class="va-staff-month-controls">
            <a href="{{ route('attendance.index', array_merge(request()->except('month', 'page'), ['month' => $start->copy()->subMonth()->format('Y-m')])) }}" class="va-staff-month-arrow" aria-label="Previous month">‹</a>
            <div class="va-staff-month-title"><strong>{{ $start->format('F Y') }}</strong><small>{{ $employees->total() }} staff</small></div>
            <a href="{{ route('attendance.index', array_merge(request()->except('month', 'page'), ['month' => $start->copy()->addMonth()->format('Y-m')])) }}" class="va-staff-month-arrow" aria-label="Next month">›</a>
            <input type="month" name="month" value="{{ $month }}" class="form-control va-staff-month-input" aria-label="Choose month">
            <button type="submit" class="btn btn-outline-primary btn-sm">Go</button>
        </form>
        <form method="GET" action="{{ route('attendance.index') }}" class="va-staff-filter-form row g-2 align-items-end">
            <input type="hidden" name="month" value="{{ $month }}">
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
