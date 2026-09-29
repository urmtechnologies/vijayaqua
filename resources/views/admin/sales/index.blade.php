@extends('layouts.admin')
@section('title', 'Sales')
@section('page-title', 'Sales')
@section('page-action')
    <div class="d-flex gap-2 flex-wrap">
        @if (\App\Support\Access::allowed('sales', 'create'))
            <a href="{{ route('sales.create') }}" class="btn btn-primary"><i class="ri-add-line me-1"></i> Add Sale</a>
        @endif
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="offcanvas" data-bs-target="#salesFilters"><i
                class="ri-filter-3-line me-1"></i> Filters</button>
    </div>
@endsection
@section('content')
    <div class="va-sales-shell" data-ledger-list>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">Sale List</h4>
                @if (\App\Support\Access::allowed('sales'))
                    @include('shared.report-exports', ['reportModule' => 'sales'])
                @endif
            </div>
            <div class="card-body">
                <form class="va-ledger-filter row g-2 align-items-end mb-3 d-none d-md-flex"
                    action="{{ route('sales.index') }}" method="GET">
                    @include('admin.sales.partials.filters', ['filterUrl' => route('sales.index')])
                </form>
                <div class="va-ledger-results va-results" aria-live="polite">@include('admin.sales.partials.results')</div>
            </div>
        </div>
        <div class="offcanvas offcanvas-end" tabindex="-1" id="salesFilters" aria-label="Sales filters">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Filter Sales</h5><button class="btn-close" type="button"
                    data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <form class="va-ledger-filter row g-3" action="{{ route('sales.index') }}" method="GET">
                    @include('admin.sales.partials.filters', ['filterUrl' => route('sales.index')])
                </form>
            </div>
        </div>
    </div>
@endsection
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">
@endpush
@push('scripts')
    <script src="{{ asset('assets/js/ledger-list.js') }}" defer></script>
@endpush
