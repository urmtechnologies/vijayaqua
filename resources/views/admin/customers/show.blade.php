@extends('layouts.admin')

@section('title', $customer->name.' · Party Account')
@section('page-title', 'Party Account')
@section('page-action')
    <div class="d-flex gap-2"><a href="{{ route('sales.create', ['mobile' => $customer->mobile]) }}" class="btn btn-primary">New Sale</a><a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">Sales List</a></div>
@endsection

@section('content')
<div class="card mb-3"><div class="card-body"><h4 class="mb-1">{{ $customer->name }}</h4><div class="text-muted">{{ $customer->mobile }} · Party #{{ $customer->id }}</div></div></div>
<div data-ledger-list>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="card-title mb-0">Invoice History</h4>
            <button type="button" class="btn btn-outline-primary d-md-none" data-bs-toggle="offcanvas" data-bs-target="#ledgerFilters">Filters</button>
        </div>
        <div class="card-body">
            <form class="va-ledger-filter row g-2 align-items-end mb-4 d-none d-md-flex" action="{{ route('customers.show', $customer) }}" method="GET">
                @include('admin.sales.partials.filters', ['filterUrl' => route('customers.show', $customer)])
            </form>
            <div class="va-ledger-results va-results" aria-live="polite">@include('admin.sales.partials.results')</div>
        </div>
    </div>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="ledgerFilters" aria-label="Invoice history filters">
        <div class="offcanvas-header"><h5 class="offcanvas-title">Filter Invoices</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
        <div class="offcanvas-body"><form class="va-ledger-filter row g-3" action="{{ route('customers.show', $customer) }}" method="GET">
            @include('admin.sales.partials.filters', ['filterUrl' => route('customers.show', $customer)])
        </form></div>
    </div>
</div>
@endsection

@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/ledger-list.js') }}" defer></script>@endpush
