@extends('layouts.admin')
@section('title', 'Attendance')
@section('page-title', 'Attendance')
@section('page-action')
@include('shared.report-exports', ['reportModule' => 'attendance'])
    @if(\App\Support\Access::allowed('attendance', 'create'))<a href="{{ route('attendance.create') }}" class="btn btn-primary">Add Attendance</a>@endif
@endsection
@section('content')
<div data-ledger-list class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><h4 class="card-title mb-0">Monthly Calendar</h4>
        <button type="button" class="btn btn-outline-primary d-md-none" data-bs-toggle="offcanvas" data-bs-target="#ledgerFilters">Filters</button></div>
    <div class="card-body">
        <p class="text-muted small">F = full day (12 hours), L = leave (unpaid), a number = hours worked. A yellow dot means admin approval is pending.</p>
        <form class="va-ledger-filter row g-2 align-items-end mb-4 d-none d-md-flex" action="{{ route('attendance.index') }}" method="GET">@include('admin.attendance.partials.filters')</form>
        <div class="va-ledger-results va-results" aria-live="polite">@include('admin.attendance.partials.results')</div>
    </div>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="ledgerFilters" aria-label="Attendance filters">
        <div class="offcanvas-header"><h5 class="offcanvas-title">Filters</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
        <div class="offcanvas-body"><form class="va-ledger-filter row g-3" action="{{ route('attendance.index') }}" method="GET">@include('admin.attendance.partials.filters')</form></div>
    </div>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/people.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/ledger-list.js') }}" defer></script>@endpush
