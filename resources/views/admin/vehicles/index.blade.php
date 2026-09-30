@extends('layouts.admin')
@section('title', 'Vehicle Entries')
@section('page-title', 'Vehicle Entries')
@section('page-action')
    @if(\App\Support\Access::allowed('vehicle-entries', 'create'))<a href="{{ route('vehicle-entries.create') }}" class="btn btn-primary"><i class="mdi mdi-plus me-1"></i>Add Entry</a>@endif
@endsection
@section('content')
<div class="card va-vehicle-card"><div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><div><h5 class="mb-1">Reference user accounts</h5><p class="text-muted small mb-0">One account for each person, with their trips and wallet in one place.</p></div>
        <form method="GET" class="va-vehicle-search d-flex gap-2" action="{{ route('vehicle-entries.index') }}"><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Search name or phone" aria-label="Search accounts"><button class="btn btn-outline-primary">Search</button></form>
    </div>
    <div id="vehicleAccounts" aria-live="polite">@include('admin.vehicles.partials.accounts')</div>
</div></div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/vehicle-entries.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/vehicle-entries.js') }}" defer></script>@endpush
