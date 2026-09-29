@extends('layouts.admin')
@section('title', $sale->is_draft ? 'Add Sale' : 'Edit '.$sale->invoice_no)
@section('page-title', $sale->is_draft ? 'Add Sale' : 'Edit Sale')
@section('page-action')<a href="{{ route('customers.show', $sale->customer) }}" class="btn btn-outline-secondary"><i class="ri-arrow-left-line me-1"></i> Party Account</a>@endsection
@section('content')
<div class="va-sales-shell">
    <div class="card mb-3"><div class="card-header"><h4 class="card-title mb-0">Party Details</h4></div>
        <div class="card-body va-party-overview">
            <div class="va-party-name"><small>Party</small><strong>{{ $sale->customer->name }}</strong><span>{{ $sale->customer->mobile }}</span></div>
            <div class="va-wallet-mini"><div><small>Approved sales</small><strong>₹{{ \App\Support\SaleMoney::format($wallet['billed']) }}</strong></div><div><small>Paid</small><strong>₹{{ \App\Support\SaleMoney::format($wallet['paid']) }}</strong></div><div><small>Balance</small><strong>₹{{ \App\Support\SaleMoney::format($wallet['balance']) }}</strong></div></div>
        </div>
    </div>
    @include('admin.sales.partials.form')
    <div class="row g-3 mb-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h4 class="card-title mb-0">Recent Sales</h4></div><div class="card-body">
            @forelse($history as $previous)<div class="va-history-line"><span><strong>{{ $previous->invoice_no }}</strong><small class="d-block text-muted">{{ $previous->sale_date->format('d M Y') }} · {{ $previous->creator?->name ?? 'System' }}</small></span><span>₹{{ \App\Support\SaleMoney::format($previous->total_rupees) }} <span class="va-status-icon {{ $previous->approval_status === 'approved' ? 'va-status-approved' : 'va-status-pending' }}" title="{{ $previous->approval_status === 'approved' ? 'Approved' : 'Pending approval' }}"><i class="{{ $previous->approval_status === 'approved' ? 'ri-checkbox-circle-line' : 'ri-time-line' }}"></i></span></span></div>
            @empty <p class="text-muted mb-0">No previous sales.</p> @endforelse
        </div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h4 class="card-title mb-0">Recent Payments</h4></div><div class="card-body">
            @forelse($paymentHistory as $payment)<div class="va-history-line"><span><strong>{{ $payment->sale?->invoice_no }}</strong><small class="d-block text-muted">{{ $payment->payment_date->format('d M Y') }} · {{ strtoupper($payment->method) }}</small></span><span>₹{{ \App\Support\SaleMoney::format($payment->amount_rupees) }} <span class="va-status-icon {{ $payment->approval_status === 'approved' ? 'va-status-approved' : 'va-status-pending' }}" title="{{ $payment->approval_status === 'approved' ? 'Approved' : 'Pending approval' }}"><i class="{{ $payment->approval_status === 'approved' ? 'ri-checkbox-circle-line' : 'ri-time-line' }}"></i></span></span></div>
            @empty <p class="text-muted mb-0">No previous payments.</p> @endforelse
        </div></div></div>
    </div>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/sales-form.js') }}" defer></script>@endpush
