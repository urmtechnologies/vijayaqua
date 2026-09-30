@extends('layouts.admin')
@section('title', 'Approvals')
@section('page-title', 'Pending Approvals')
@section('content')
<div class="card"><div class="card-body">
    <p class="text-muted mb-3">Review staff entries before they affect stock, payments or salary.</p>
    <div id="approvalWorkspace" class="va-approval-workspace position-relative" aria-live="polite">
        @include('admin.approvals.partials.workspace')
    </div>
</div></div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/approvals.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/approvals.js') }}" defer></script>@endpush
