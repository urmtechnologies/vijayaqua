@extends('layouts.admin')
@section('title', $entry ? 'Edit Vehicle Entry' : 'Add Vehicle Entry')
@section('page-title', $entry ? 'Edit Vehicle Entry' : 'Add Vehicle Entry')
@section('page-action')<a href="{{ $entry ? route('vehicle-entries.account', $entry->reference_user_id) : route('vehicle-entries.index') }}" class="btn btn-outline-secondary">Back</a>@endsection
@section('content')
@php
    $rows = old('items', $entry?->items->map(fn ($item) => ['product_id' => $item->product_id, 'cartons' => $item->cartons])->all() ?? []);
    $rows = $rows ?: [['product_id' => '', 'cartons' => '']];
@endphp
<form id="vehicleEntryForm" method="POST" action="{{ $entry ? route('vehicle-entries.update', $entry) : route('vehicle-entries.store') }}" class="card va-vehicle-card">
    @csrf @if($entry) @method('PUT') @endif
    <div class="card-body">
        <h5 class="mb-1">{{ $entry ? 'Update trip' : 'New trip' }}</h5><p class="text-muted small mb-4">Record the route, amount and cartons taken from the plant.</p>
        <div class="row g-3">
            <div class="col-md-6"><label for="vehicleTo" class="form-label">To destination *</label><input id="vehicleTo" name="to_destination" class="form-control" maxlength="150" value="{{ old('to_destination', $entry?->to_destination) }}" required></div>
            <div class="col-md-6"><label for="vehicleFrom" class="form-label">From destination *</label><input id="vehicleFrom" name="from_destination" class="form-control" maxlength="150" value="{{ old('from_destination', $entry?->from_destination ?? 'Plant') }}" required></div>
            <div class="col-sm-4"><label for="vehicleAmount" class="form-label">Amount (₹) *</label><input type="number" step="0.01" min="0.01" id="vehicleAmount" name="amount_rupees" class="form-control" value="{{ old('amount_rupees', $entry?->amount_rupees) }}" required></div>
            <div class="col-sm-4"><label for="vehicleDate" class="form-label">Date *</label><input type="date" id="vehicleDate" name="entry_date" class="form-control" value="{{ old('entry_date', $entry?->entry_date?->format('Y-m-d') ?? now()->toDateString()) }}" required></div>
            <div class="col-sm-4"><label for="vehiclePerson" class="form-label">Reference user *</label><select id="vehiclePerson" name="reference_user_id" class="form-select" required>
                <option value="">Choose staff member</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((string) old('reference_user_id', $selectedUser) === (string) $person->id)>{{ $person->name }} · {{ $person->mobile }}</option>@endforeach
            </select></div>
        </div>
        <div class="va-vehicle-items mt-4"><div class="d-flex align-items-center justify-content-between"><h6 class="mb-2">Products carried</h6><span class="text-muted small">CTN</span></div>
            <div id="vehicleRows">
            @foreach($rows as $index => $row)
                <div class="va-vehicle-row" data-vehicle-row><span class="va-vehicle-check"><i class="mdi mdi-package-variant-closed"></i></span>
                    <select name="items[{{ $index }}][product_id]" class="form-select va-vehicle-product" aria-label="Product" required>
                        <option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) ($row['product_id'] ?? '') === (string) $product->id)>{{ $product->name }}</option>@endforeach
                    </select>
                    <input type="number" min="1" max="1000000000" step="1" name="items[{{ $index }}][cartons]" class="form-control va-vehicle-qty" placeholder="Qty CTN" aria-label="Quantity in cartons" value="{{ $row['cartons'] ?? '' }}" required>
                    <button class="btn btn-sm btn-light va-vehicle-remove" type="button" aria-label="Remove product"><i class="mdi mdi-close"></i></button>
                </div>
            @endforeach
            </div>
            <button type="button" class="va-vehicle-add" id="addVehicleRow"><i class="mdi mdi-plus-circle-outline"></i> Add product</button>
            @error('items')<p class="text-danger small">{{ $message }}</p>@enderror
            @if($products->isEmpty())<p class="text-danger small">Add an approved active product before recording a trip.</p>@endif
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center gap-2 flex-wrap"><small class="text-muted">{{ auth()->user()->role === 'admin' ? 'Admin entries count immediately.' : 'Entry will count after admin approval.' }}</small>
        <button id="submitVehicleEntry" type="submit" class="btn btn-primary" @disabled($products->isEmpty())><span class="spinner-border spinner-border-sm d-none me-1" id="vehicleSubmitSpinner"></span>{{ $entry ? 'Save Changes' : (auth()->user()->role === 'admin' ? 'Save Entry' : 'Send for Approval') }}</button>
    </div>
</form>
<template id="vehicleRowTemplate"><div class="va-vehicle-row" data-vehicle-row><span class="va-vehicle-check"><i class="mdi mdi-package-variant-closed"></i></span>
    <select class="form-select va-vehicle-product" aria-label="Product" required><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select>
    <input type="number" min="1" max="1000000000" step="1" class="form-control va-vehicle-qty" placeholder="Qty CTN" aria-label="Quantity in cartons" required>
    <button class="btn btn-sm btn-light va-vehicle-remove" type="button" aria-label="Remove product"><i class="mdi mdi-close"></i></button>
</div></template>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/vehicle-entries.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/vehicle-entries.js') }}" defer></script>@endpush
