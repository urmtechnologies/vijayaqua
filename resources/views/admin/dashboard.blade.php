@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('content')
    <div class="va-dashboard-hero mb-4">
        <div>
            <span
                class="va-dashboard-kicker">{{ auth()->user()->role === 'admin' ? 'ADMIN WORKSPACE' : 'MY WORKSPACE' }}</span>
            <h2 class="mt-2 mb-2 text-white">Hello, {{ auth()->user()->name }}</h2>
            <p class="mb-0">Today is {{ now()->format('d M Y') }}. Open a card to see its records and filters.</p>
        </div>
        @if ($canSeeReports)
            <a class="btn btn-light" href="{{ route('reports.index') }}"><i class="mdi mdi-chart-box-outline me-1"></i> Open
                Reports</a>
        @endif
    </div>
    @if ($pending !== null && $pending > 0)
        <a href="{{ route('approvals.index') }}"
            class="alert alert-warning d-flex justify-content-between align-items-center text-decoration-none mb-4">
            <span><i class="mdi mdi-clock-check-outline me-2"></i><strong>{{ number_format($pending) }} pending
                    approvals</strong> need review.</span>
            <span>Review <i class="mdi mdi-arrow-right"></i></span>
        </a>
    @endif
    @if (count($cards))
        <h4 class="mb-3">Overview</h4>
        <div class="row g-3 mb-4">
            @foreach ($cards as $card)
                <div class="col-12 col-sm-6 col-xl-4">
                    <a class="va-dashboard-card" href="{{ route($card['route']) }}">
                        <div class="va-card-top"><span class="va-card-icon"><i class="mdi {{ $card['icon'] }}"></i></span><i
                                class="mdi mdi-arrow-top-right va-arrow"></i></div>
                        <div class="va-card-label">{{ $card['label'] }}</div>
                        <div class="va-card-value">{{ $card['value'] }}</div>
                        <div class="va-card-help">{{ $card['hint'] }}</div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
    @if (count($shortcuts))
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Quick actions</h4>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($shortcuts as $shortcut)
                        <a class="va-quick-link" href="{{ route($shortcut['route']) }}"><i class="mdi mdi-plus"></i>
                            {{ $shortcut['label'] }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    @elseif(!count($cards))
        <div class="card">
            <div class="card-body">Your account does not have module access yet. Contact an admin to assign permissions.
            </div>
        </div>
    @endif
@endsection
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/reports.css') }}">
@endpush
