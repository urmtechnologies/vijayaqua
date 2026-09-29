@extends('layouts.admin')
@section('title', 'Add Sale')
@section('page-title', 'Add Sale')
@section('page-action')<a href="{{ route('sales.index') }}" class="btn btn-outline-secondary"><i class="ri-arrow-left-line me-1"></i> Back to Sales</a>@endsection
@section('content')
<div class="va-sales-shell">
    <div class="card va-party-start">
        <div class="card-header"><h4 class="card-title mb-0">Party Details</h4></div>
        <form method="POST" action="{{ route('sales.store') }}" data-customer-lookup="{{ route('customers.lookup') }}" id="partyStartForm">
            @csrf
            <div class="card-body row g-3">
                <div class="col-12 col-md-4">
                    <label for="partyMobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                    <input type="tel" id="partyMobile" name="mobile" class="form-control @error('mobile') is-invalid @enderror" value="{{ old('mobile', $customer?->mobile) }}" pattern="[0-9]{10}" maxlength="10" inputmode="numeric" autocomplete="tel" required>
                    @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-4">
                    <label for="partyName" class="form-label">Party Name <span class="text-danger">*</span></label>
                    <input id="partyName" name="party_name" class="form-control @error('party_name') is-invalid @enderror" value="{{ old('party_name', $customer?->name) }}" maxlength="150" required>
                    @error('party_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-4">
                    <label for="saleDate" class="form-label">Sale Date <span class="text-danger">*</span></label>
                    <input type="date" id="saleDate" name="sale_date" class="form-control @error('sale_date') is-invalid @enderror" value="{{ old('sale_date', now()->toDateString()) }}" required>
                    @error('sale_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12"><small id="partyStartHint" class="text-muted" aria-live="polite"></small></div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2 flex-wrap">
                <a href="{{ route('sales.index') }}" class="btn btn-light">Cancel</a>
                <button class="btn btn-primary" type="submit">Continue <i class="ri-arrow-right-line ms-1"></i></button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/party-start.js') }}" defer></script>@endpush
