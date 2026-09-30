@extends('layouts.admin')
@section('title', $user->name.' · Vehicle Account')
@section('page-title', 'Vehicle Account')
@section('page-action')<div class="d-flex gap-2">
    @if(\App\Support\Access::allowed('vehicle-entries', 'create'))<a href="{{ route('vehicle-entries.create', ['user' => $user->id]) }}" class="btn btn-primary">+ Add Entry</a>@endif
    <a href="{{ route('vehicle-entries.index') }}" class="btn btn-outline-secondary">All Accounts</a>
</div>@endsection
@section('content')
<div class="card va-vehicle-card mb-3"><div class="card-body va-vehicle-header">
    <div><span class="text-muted small text-uppercase">Reference user</span><h4 class="mb-1">{{ $user->name }}</h4><span class="text-muted">{{ $user->mobile }} · {{ ucfirst($user->role) }}</span></div>
    <div class="va-vehicle-wallet"><span><small>Approved amount</small><strong>₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($wallet['charged'])) }}</strong></span>
        <span class="text-success"><small>Paid</small><strong>₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($wallet['paid'])) }}</strong></span>
        <span class="{{ $wallet['balance'] > 0 ? 'text-danger' : 'text-success' }}"><small>Balance left</small><strong>₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($wallet['balance'])) }}</strong></span></div>
</div></div>
<div class="card va-vehicle-card mb-3"><div class="card-body"><h5 class="mb-3">Vehicle entries</h5>
    @forelse($entries as $entry)
        <article class="va-vehicle-trip">
            <div class="va-vehicle-trip-main"><span class="text-muted small">{{ $entry->entry_date->format('d M Y') }} · #{{ $entry->id }}</span>
                <strong>{{ $entry->from_destination }} <span aria-hidden="true">→</span> {{ $entry->to_destination }}</strong>
                <div class="text-muted small">@foreach($entry->items as $item){{ $item->product_name }} × {{ $item->cartons }} CTN{{ !$loop->last ? ' · ' : '' }}@endforeach</div>
                <small>Added by {{ $entry->creator?->name ?? 'System' }}</small></div>
            <div class="va-vehicle-trip-side"><strong>₹{{ \App\Support\SaleMoney::format($entry->amount_rupees) }}</strong>
                @include('shared.approval-status', ['record' => $entry])
                <div class="d-flex gap-2">@if(\App\Support\Access::canEdit('vehicle-entries', $entry))<a href="{{ route('vehicle-entries.edit', $entry) }}" class="btn btn-sm btn-outline-primary">Edit</a>@endif
                @if(\App\Support\Access::canDelete('vehicle-entries', $entry))<form method="POST" action="{{ route('vehicle-entries.destroy', $entry) }}" onsubmit="return confirm('Delete this vehicle entry?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endif</div></div>
        </article>
    @empty <p class="text-muted">No entries yet.</p>
    @endforelse
    @include('shared.pagination', ['paginator' => $entries, 'label' => 'Vehicle entries pagination'])
</div></div>
<div class="card va-vehicle-card"><div class="card-body"><h5>Payment history</h5>
    <p class="text-muted small">Credit = paid to this user. Debit = a payment returned. Only approved entry amounts enter the wallet.</p>
    @if(auth()->user()->role === 'admin' && ! $user->trashed())
    <form method="POST" action="{{ route('vehicle-entries.payment', $user) }}" class="va-vehicle-payment-form row g-2 align-items-end mb-4" data-safe-submit>@csrf
        <div class="col-6 col-lg-2"><label class="form-label" for="walletDate">Date</label><input type="date" name="payment_date" id="walletDate" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="walletType">Entry</label><select name="entry_type" id="walletType" class="form-select"><option value="credit">Credit · pay</option><option value="debit">Debit · returned</option></select></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="walletAmount">Amount (₹)</label><input name="amount_rupees" id="walletAmount" type="number" step="0.01" min="0.01" class="form-control" required></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="walletMethod">Method</label><select name="method" id="walletMethod" class="form-select"><option value="cash">Cash</option><option value="upi">UPI</option><option value="bank">Bank</option><option value="other">Other</option></select></div>
        <div class="col-12 col-lg-2"><label class="form-label" for="walletNote">Note</label><input name="note" id="walletNote" maxlength="500" class="form-control"></div>
        <div class="col-12 col-lg-2"><button class="btn btn-primary w-100" data-submit-button>Record Payment</button></div>
    </form>
    @endif
    @forelse($payments as $payment)
        <div class="va-vehicle-payment"><span><strong>{{ $payment->payment_date->format('d M Y') }}</strong><small>{{ ucfirst($payment->method) }} · {{ $payment->creator?->name ?? 'Admin' }}{{ $payment->note ? ' · '.$payment->note : '' }}</small></span>
            <span class="{{ $payment->entry_type === 'credit' ? 'text-success' : 'text-danger' }}">{{ $payment->entry_type === 'credit' ? 'Credit' : 'Debit' }} ₹{{ \App\Support\SaleMoney::format($payment->amount_rupees) }}</span></div>
    @empty <p class="text-muted py-3">No payments yet.</p>
    @endforelse
    @include('shared.pagination', ['paginator' => $payments, 'label' => 'Vehicle payment pages'])
</div></div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/vehicle-entries.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/record-form.js') }}" defer></script>@endpush
