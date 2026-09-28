@extends('layouts.admin')
@section('title', 'Partner Ledger')
@section('page-title', 'Partner Ledger')
@section('page-action')
@if(\App\Support\Access::allowed('partner-ledger', 'create'))<a href="{{ route('partner-ledger.create') }}" class="btn btn-primary"><i class="mdi mdi-plus me-1"></i> Add Entry</a>@endif
@endsection
@section('content')
<div data-ledger-list class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2"><h4 class="card-title mb-0">Send & Receive History</h4><button type="button" class="btn btn-outline-primary d-md-none" data-bs-toggle="offcanvas" data-bs-target="#ledgerFilters">Filters</button></div>
    <div class="card-body">
        <form class="va-ledger-filter row g-2 align-items-end mb-4 d-none d-md-flex" method="GET" action="{{ route('partner-ledger.index') }}">@include('admin.partner-ledger.partials.filters', ['filterUrl' => route('partner-ledger.index')])</form>
        <div class="va-ledger-results va-results" aria-live="polite">@include('admin.partner-ledger.partials.results')</div>
    </div>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="ledgerFilters" aria-label="Partner ledger filters"><div class="offcanvas-header"><h5 class="offcanvas-title">Filter Transactions</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
        <div class="offcanvas-body"><form class="va-ledger-filter row g-3" method="GET" action="{{ route('partner-ledger.index') }}">@include('admin.partner-ledger.partials.filters', ['filterUrl' => route('partner-ledger.index')])</form></div>
    </div>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/operations.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/ledger-list.js') }}" defer></script>@endpush
