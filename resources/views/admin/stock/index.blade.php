@extends('layouts.admin')

@section('title', 'Stock Entries')
@section('page-title', 'Stock Entries')
@section('page-action')
@include('shared.report-exports', ['reportModule' => 'stock-entries'])
    @if(\App\Support\Access::allowed('stock-entries', 'create'))<a href="{{ route('stock-entries.create') }}" class="btn btn-primary"><i class="mdi mdi-plus me-1"></i> Add Stock Entry</a>@endif
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="card-title mb-0">Daily Stock Entries</h4>
        <button type="button" class="btn btn-outline-primary d-md-none" data-bs-toggle="offcanvas" data-bs-target="#stockFilters" aria-controls="stockFilters">
            <i class="mdi mdi-filter-variant me-1"></i> Filters
        </button>
    </div>
    <div class="card-body">
        <form id="desktopStockFilters" class="row g-2 align-items-end mb-4 d-none d-md-flex" action="{{ route('stock-entries.index') }}" method="GET">
            @include('admin.stock.partials.filters')
        </form>
        <div id="stockResults" class="va-results" aria-live="polite">
            @include('admin.stock.partials.results')
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="stockFilters" aria-labelledby="stockFiltersLabel">
    <div class="offcanvas-header">
        <h5 id="stockFiltersLabel" class="offcanvas-title">Filter Stock Entries</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="mobileStockFilters" action="{{ route('stock-entries.index') }}" method="GET" class="row g-3">
            @include('admin.stock.partials.filters')
        </form>
    </div>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/stock.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/stock-list.js') }}" defer></script>
@endpush
