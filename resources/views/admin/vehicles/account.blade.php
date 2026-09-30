@extends('layouts.admin')
@section('title', $user->name.' · Vehicle Account')
@section('page-title', 'Vehicle Account')
@section('page-action')<div class="d-flex gap-2">
    @if(auth()->user()->role !== 'admin' && (int) auth()->id() === (int) $user->id && ! $user->trashed() && $user->approval_status === 'approved' && \App\Support\Access::allowed('vehicle-entries', 'create'))<a href="{{ route('vehicle-entries.create') }}" class="btn btn-primary">+ Add Entry</a>@endif
    <a href="{{ route('vehicle-entries.index') }}" class="btn btn-outline-secondary">All Entries</a>
</div>@endsection
@section('content')
<div id="vehicleWorkspace" class="va-vehicle-workspace" aria-live="polite">@include('admin.vehicles.partials.account-workspace')</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/vehicle-entries.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/vehicle-entries.js') }}" defer></script>@endpush
