@extends('layouts.admin')
@section('title', 'Salary')
@section('page-title', 'Salary')
@section('page-action')@if(auth()->user()->role === 'admin')<a href="{{ route('salaries.create') }}" class="btn btn-primary">Generate Salary</a>@endif @endsection
@section('content')
<div data-ledger-list class="card"><div class="card-header d-flex justify-content-between align-items-center"><h4 class="card-title mb-0">Monthly Salary Records</h4><button type="button" class="btn btn-outline-primary d-md-none" data-bs-toggle="offcanvas" data-bs-target="#ledgerFilters">Filters</button></div>
    <div class="card-body">
        <form class="va-ledger-filter row g-2 align-items-end mb-4 d-none d-md-flex" action="{{ route('salaries.index') }}" method="GET">@include('admin.salaries.partials.filters')</form>
        <div class="va-ledger-results va-results" aria-live="polite">@include('admin.salaries.partials.results')</div>
    </div>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="ledgerFilters" aria-label="Salary filters"><div class="offcanvas-header"><h5 class="offcanvas-title">Filters</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
        <div class="offcanvas-body"><form class="va-ledger-filter row g-3" action="{{ route('salaries.index') }}" method="GET">@include('admin.salaries.partials.filters')</form></div>
    </div>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/people.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/ledger-list.js') }}" defer></script>@endpush
