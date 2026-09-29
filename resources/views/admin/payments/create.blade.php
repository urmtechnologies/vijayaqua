@extends('layouts.admin')

@section('title', 'Add Payment')
@section('page-title', 'Add Payment')
@section('page-action')<a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Back to Payments</a>@endsection

@section('content')
<div class="row"><div class="col-xl-8 col-xxl-7"><div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Record Payment</h4></div>
    <form id="paymentForm" action="{{ route('payments.store') }}" method="POST" data-lookup-url="{{ route('payments.lookup') }}">
        @csrf
        <div class="card-body row g-3">
            <div class="col-sm-6"><label class="form-label" for="paymentType">Entry Type <span class="text-danger">*</span></label><select id="paymentType" name="entry_type" class="form-select" required><option value="credit" @selected(old('entry_type') === 'credit')>Credit (received)</option><option value="debit" @selected(old('entry_type') === 'debit')>Debit (returned)</option></select></div>
            <div class="col-12"><label class="form-label" for="paymentInvoice">Invoice Number <span class="text-danger">*</span></label>
                <input type="search" id="paymentInvoice" name="invoice" class="form-control" value="{{ old('invoice', $sale?->invoice_no) }}" placeholder="VA-INV-000001" autocomplete="off" required>
                <input type="hidden" id="paymentSaleId" name="sale_id" value="{{ old('sale_id', $sale?->id) }}">
                @error('sale_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-12"><div id="paymentInvoiceInfo" class="va-party-lookup" aria-live="polite"></div></div>
            <div class="col-sm-6"><label class="form-label" for="paymentDate">Payment Date <span class="text-danger">*</span></label>
                <input type="date" id="paymentDate" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', now()->toDateString()) }}" required>
                @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6"><label class="form-label" for="paymentAmount">Amount (₹) <span class="text-danger">*</span></label>
                <input type="number" id="paymentAmount" name="amount_rupees" class="form-control @error('amount_rupees') is-invalid @enderror" min="0.01" step="0.01" value="{{ old('amount_rupees') }}" required>
                @error('amount_rupees')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6"><label class="form-label" for="paymentMethod">Payment Method <span class="text-danger">*</span></label>
                <select id="paymentMethod" name="method" class="form-select @error('method') is-invalid @enderror" required>
                    <option value="">Select method</option>
                    @foreach(['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6"><label class="form-label" for="paymentReference">Reference (optional)</label>
                <input id="paymentReference" name="reference" class="form-control" maxlength="150" value="{{ old('reference') }}">
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('payments.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary" id="savePayment"><span class="spinner-border spinner-border-sm me-1 d-none" id="paymentSpinner" aria-hidden="true"></span><span id="paymentButtonText">Record Payment</span></button>
        </div>
    </form>
</div></div></div>
@endsection

@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/payments-form.js') }}" defer></script>@endpush
