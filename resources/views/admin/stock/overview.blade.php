@extends('layouts.admin')

@section('title', 'Stock Overview')
@section('page-title', 'Stock Overview')
@section('page-action')<a href="{{ route('stock-entries.create') }}" class="btn btn-primary"><i class="mdi mdi-plus me-1"></i> Add Stock Entry</a>@endsection

@section('content')
<div data-ledger-list class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">Product-wise Stock</h4>
        <button type="button" class="btn btn-outline-primary d-md-none" data-bs-toggle="offcanvas" data-bs-target="#ledgerFilters">Filters</button>
    </div>
    <div class="card-body">
        <form class="va-ledger-filter row g-2 align-items-end mb-4 d-none d-md-flex" method="GET" action="{{ route('stock.overview') }}">@include('admin.stock.partials.overview-filters')</form>
        <div class="va-ledger-results va-results" aria-live="polite">@include('admin.stock.partials.overview-results')</div>
    </div>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="ledgerFilters" aria-label="Stock overview filters">
        <div class="offcanvas-header"><h5 class="offcanvas-title">Filter Stock</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
        <div class="offcanvas-body"><form class="va-ledger-filter row g-3" method="GET" action="{{ route('stock.overview') }}">@include('admin.stock.partials.overview-filters')</form></div>
    </div>
</div>
@endsection

@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/stock.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/ledger-list.js') }}" defer></script>@endpush
