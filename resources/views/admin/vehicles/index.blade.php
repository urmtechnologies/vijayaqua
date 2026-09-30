@extends('layouts.admin')
@section('title', 'Vehicle Entries')
@section('page-title', 'Vehicle Entries')
@section('page-action')
    @if(auth()->user()->role !== 'admin' && \App\Support\Access::allowed('vehicle-entries', 'create'))
        <a href="{{ route('vehicle-entries.create') }}" class="btn btn-primary"><i class="mdi mdi-plus me-1"></i>Add Entry</a>
    @endif
@endsection
@section('content')
<div class="card va-vehicle-card"><div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h5 class="mb-0">Vehicle entries</h5>
        <form method="GET" class="va-vehicle-search" action="{{ route('vehicle-entries.index') }}">
            <input name="search" class="form-control" maxlength="100" value="{{ request('search') }}" placeholder="Name, phone or destination" aria-label="Search entries">
            <select name="approval" class="form-select" aria-label="Approval status"><option value="">All entries</option><option value="pending" @selected(request('approval') === 'pending')>Pending</option><option value="approved" @selected(request('approval') === 'approved')>Approved</option></select>
            <button class="btn btn-outline-primary">Search</button>
        </form>
    </div>
    <div id="vehicleAccounts" class="va-vehicle-workspace" aria-live="polite">@include('admin.vehicles.partials.entries')</div>
</div></div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/vehicle-entries.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/vehicle-entries.js') }}" defer></script>@endpush
