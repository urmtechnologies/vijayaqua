@extends('layouts.admin')
@section('title', $partner->name.' · Partner Ledger')
@section('page-title', 'Partner Account')
@section('page-action')<div class="d-flex gap-2"><a href="{{ route('partner-ledger.create', ['partner' => $partner->id]) }}" class="btn btn-primary">Add Entry</a><a href="{{ route('partner-ledger.index') }}" class="btn btn-outline-secondary">All Partners</a></div>@endsection
@section('content')
<div class="card mb-3"><div class="card-body"><h4 class="mb-1">{{ $partner->name }}</h4><p class="text-muted mb-0">Send and receive history for this partner. The balance below follows the active filters.</p></div></div>
<div data-ledger-list class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2"><h4 class="card-title mb-0">Transactions</h4><button type="button" class="btn btn-outline-primary d-md-none" data-bs-toggle="offcanvas" data-bs-target="#ledgerFilters">Filters</button></div>
    <div class="card-body"><form class="va-ledger-filter row g-2 align-items-end mb-4 d-none d-md-flex" method="GET" action="{{ route('partners.show', $partner) }}">@include('admin.partner-ledger.partials.filters', ['filterUrl' => route('partners.show', $partner)])</form>
        <div class="va-ledger-results va-results" aria-live="polite">@include('admin.partner-ledger.partials.results')</div>
    </div>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="ledgerFilters" aria-label="Partner filters"><div class="offcanvas-header"><h5 class="offcanvas-title">Filter Transactions</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
        <div class="offcanvas-body"><form class="va-ledger-filter row g-3" method="GET" action="{{ route('partners.show', $partner) }}">@include('admin.partner-ledger.partials.filters', ['filterUrl' => route('partners.show', $partner)])</form></div>
    </div>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/partner-attachments.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/operations.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/ledger-list.js') }}" defer></script>@endpush
