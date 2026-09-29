@extends('layouts.admin')

@section('title', 'Sales List')
@section('page-title', 'Sales List')
@section('page-action')
@include('shared.report-exports', ['reportModule' => 'sales'])
    @if(\App\Support\Access::allowed('sales', 'create'))<a href="{{ route('sales.create') }}" class="btn btn-primary"><i class="mdi mdi-plus me-1"></i> Add Sale</a>@endif
@endsection

@section('content')
<div data-ledger-list>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="card-title mb-0">Invoices</h4>
            <button type="button" class="btn btn-outline-primary d-md-none" data-bs-toggle="offcanvas" data-bs-target="#ledgerFilters">Filters</button>
        </div>
        <div class="card-body">
            <form class="va-ledger-filter row g-2 align-items-end mb-4 d-none d-md-flex" action="{{ route('sales.index') }}" method="GET">
                @include('admin.sales.partials.filters', ['filterUrl' => route('sales.index')])
            </form>
            <div class="va-ledger-results va-results" aria-live="polite">@include('admin.sales.partials.results')</div>
        </div>
    </div>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="ledgerFilters" aria-label="Sales filters">
        <div class="offcanvas-header"><h5 class="offcanvas-title">Filter Sales</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
        <div class="offcanvas-body"><form class="va-ledger-filter row g-3" action="{{ route('sales.index') }}" method="GET">
            @include('admin.sales.partials.filters', ['filterUrl' => route('sales.index')])
        </form></div>
    </div>
</div>
@endsection

@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/ledger-list.js') }}" defer></script>@endpush
