@extends('layouts.admin')
@section('title', 'Edit Payment')
@section('page-title', 'Edit Payment')
@section('page-action')<a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Back to Payments</a>@endsection
@section('content')
<div class="row"><div class="col-xl-7"><div class="card"><div class="card-body">
    <p><strong>{{ $payment->sale->invoice_no }}</strong> · {{ $payment->sale->customer->name }} @include('admin.sales.partials.status-icon', ['record' => $payment])</p>
    <form method="POST" action="{{ route('payments.update', $payment) }}" data-safe-submit>@csrf @method('PUT')
        <div class="row g-3"><div class="col-sm-6"><label class="form-label">Entry type</label><select name="entry_type" class="form-select"><option value="credit" @selected(old('entry_type', $payment->entry_type) === 'credit')>Credit (received)</option><option value="debit" @selected(old('entry_type', $payment->entry_type) === 'debit')>Debit (returned)</option></select></div><div class="col-sm-6"><label class="form-label">Payment date</label><input type="date" class="form-control" name="payment_date" value="{{ old('payment_date', $payment->payment_date->format('Y-m-d')) }}" required></div>
            <div class="col-sm-6"><label class="form-label">Amount (₹)</label><input type="number" class="form-control" name="amount_rupees" min="0.01" step="0.01" value="{{ old('amount_rupees', $payment->amount_rupees) }}" required></div>
            <div class="col-sm-6"><label class="form-label">Method</label><select name="method" class="form-select" required>@foreach(['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank', 'other' => 'Other'] as $key => $label)<option value="{{ $key }}" @selected(old('method', $payment->method) === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-sm-6"><label class="form-label">Reference</label><input name="reference" class="form-control" value="{{ old('reference', $payment->reference) }}" maxlength="150"></div>
        </div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" data-submit-button><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span> <span data-submit-label>Save Payment</span></button><a href="{{ route('payments.index') }}" class="btn btn-light">Cancel</a></div>
    </form>
</div></div></div></div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/record-form.js') }}" defer></script>@endpush
