@extends('layouts.admin')

@section('title', $sale->invoice_no)
@section('page-title', 'Invoice '.$sale->invoice_no)
@section('page-action')
    <div class="d-flex gap-2 va-no-print">
        @if($sale->approval_status === 'approved')<button type="button" class="btn btn-outline-primary" id="printInvoice">Print Invoice</button>@endif
        @if(\App\Support\Access::canEdit('sales', $sale))<a href="{{ route('sales.edit', $sale) }}" class="btn btn-outline-secondary">Edit Sale</a>@endif
        @if($sale->payments->isEmpty())
            @if(\App\Support\Access::canDelete('sales', $sale))<form method="POST" action="{{ route('sales.destroy', $sale) }}" class="d-inline">@csrf @method('DELETE')
                <button type="button" class="btn btn-outline-danger" data-va-delete data-va-delete-name="{{ $sale->invoice_no }}">Delete</button>
            </form>@endif
        @endif
        <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">Sales List</a>
    </div>
@endsection

@section('content')
<p>@include('shared.approval-status', ['record' => $sale])</p>
@if($sale->approval_status === 'pending')<div class="alert alert-warning va-no-print">This invoice is waiting for admin approval. Stock and payment totals will update after approval.</div>@endif
@php $due = (int) $sale->total_rupees - $paid; @endphp
<div class="card va-invoice">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between flex-wrap gap-2 border-bottom pb-3 mb-4">
            <div><h3 class="mb-1">Vijay Aqua</h3><p class="text-muted mb-0">Sales Invoice</p></div>
            <div class="text-end"><h4 class="mb-1">{{ $sale->invoice_no }}</h4><div>{{ $sale->sale_date->format('d M Y') }}</div></div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-6"><small class="text-muted">Bill to</small><h5 class="mb-1">{{ $sale->customer->name }}</h5>
                <div>{{ $sale->customer->mobile }}</div>@if(\App\Support\Access::allowed('sales'))<a class="va-no-print" href="{{ route('customers.show', $sale->customer) }}">View party account</a>@endif
            </div>
            <div class="col-md-6 text-md-end"><small class="text-muted">Recorded by</small><div>{{ $sale->creator?->name ?? 'System' }}</div>
                @if($sale->updated_by)<div class="small text-muted">Edited by {{ $sale->editor?->name ?? 'System' }}</div>@endif
                @if($sale->reference)<div>Reference: {{ $sale->reference }}</div>@endif
            </div>
        </div>
        <div class="table-responsive"><table class="table align-middle">
            <thead class="table-light"><tr><th>Product</th><th class="text-end">Qty (CTN)</th><th class="text-end">Rate (₹)</th><th class="text-end">Amount (₹)</th></tr></thead>
            <tbody>
                @foreach($sale->items as $item)
                    <tr><td>{{ $item->product_name }}</td><td class="text-end">{{ \App\Support\CartonNumber::format($item->cartons) }}</td>
                        <td class="text-end">{{ \App\Support\RupeeAmount::format($item->rate_rupees) }}</td>
                        <td class="text-end">{{ \App\Support\RupeeAmount::format($item->line_total_rupees) }}</td></tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="row justify-content-end"><div class="col-md-5 col-lg-4">
            <div class="va-invoice-total"><span>Subtotal</span><strong>₹{{ \App\Support\RupeeAmount::format($sale->subtotal_rupees) }}</strong></div>
            <div class="va-invoice-total"><span>Discount</span><strong>− ₹{{ \App\Support\RupeeAmount::format($sale->discount_rupees) }}</strong></div>
            <div class="va-invoice-total"><span>Vehicle charge</span><strong>₹{{ \App\Support\RupeeAmount::format($sale->vehicle_charge_rupees) }}</strong></div>
            <div class="va-invoice-total va-invoice-final"><span>Invoice total</span><strong>₹{{ \App\Support\RupeeAmount::format($sale->total_rupees) }}</strong></div>
            <div class="va-invoice-total"><span>Paid</span><strong>₹{{ \App\Support\RupeeAmount::format($paid) }}</strong></div>
            <div class="va-invoice-total"><span>Due</span><strong>₹{{ \App\Support\RupeeAmount::format($due) }}</strong></div>
            @if($due > 0 && $sale->due_date)<div class="text-end small text-muted">Due date: {{ $sale->due_date->format('d M Y') }}</div>@endif
        </div></div>
    </div>
</div>

<div class="card mt-3 va-no-print">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">Payments</h4>
        @if($due > 0 && $sale->approval_status === 'approved' && \App\Support\Access::allowed('payments', 'create'))<a href="{{ route('payments.create', ['invoice' => $sale->invoice_no]) }}" class="btn btn-primary btn-sm">Record Payment</a>@endif
    </div>
    <div class="card-body">
        @forelse($sale->payments as $payment)
            <div class="d-flex justify-content-between flex-wrap gap-2 border-bottom py-2">
                <div><strong>{{ $payment->payment_date->format('d M Y') }}</strong><span class="text-muted ms-2">{{ strtoupper($payment->method) }}</span> @include('shared.approval-status', ['record' => $payment])
                    @if($payment->reference)<div class="text-muted small">Ref: {{ $payment->reference }}</div>@endif
                    <small class="text-muted">Recorded by {{ $payment->creator?->name ?? 'System' }}</small></div>
                <strong>₹{{ \App\Support\RupeeAmount::format($payment->amount_rupees) }}</strong>
            </div>
        @empty
            <p class="text-muted mb-0">No payment received yet.</p>
        @endforelse
    </div>
</div>
<div class="mt-3 va-no-print">@if(\App\Support\Access::allowed('sales', 'create'))<a href="{{ route('sales.create', ['mobile' => $sale->customer->mobile]) }}" class="btn btn-outline-primary">New Sale for This Party</a>@endif</div>
@endsection

@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/sales-invoice.js') }}" defer></script>@endpush
