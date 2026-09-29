@extends('layouts.admin')
@section('title', $customer->name.' · Sales')
@section('page-title', 'Party Sales')
@section('page-action')<div class="d-flex gap-2 flex-wrap">
    @if(\App\Support\Access::allowed('sales', 'create'))<a href="{{ route('sales.create', ['mobile' => $customer->mobile]) }}" class="btn btn-primary"><i class="ri-add-line me-1"></i> Add Sale</a>@endif
    <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary"><i class="ri-arrow-left-line me-1"></i> Sales</a>
</div>@endsection
@section('content')
<div class="va-sales-shell">
    <div class="card mb-3"><div class="card-body va-party-overview"><div class="va-party-name"><small>Party account</small><strong>{{ $customer->name }}</strong><span>{{ $customer->mobile }}</span></div><div class="va-wallet-mini">
        <div><small>Approved sales</small><strong>₹{{ \App\Support\SaleMoney::format($wallet['billed']) }}</strong></div>
        <div><small>Received</small><strong>₹{{ \App\Support\SaleMoney::format($wallet['paid']) }}</strong></div>
        <div><small>Balance</small><strong>₹{{ \App\Support\SaleMoney::format($wallet['balance']) }}</strong></div>
    </div></div></div>
    @if($draft && \App\Support\Access::canEdit('sales', $draft))
    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2"><span><i class="ri-draft-line me-1"></i> {{ $draft->invoice_no }} is unfinished.</span><a href="{{ route('sales.edit', $draft) }}" class="btn btn-sm btn-outline-primary">Continue sale</a></div>
    @endif
    @if($pendingCount)<div class="alert alert-warning"><i class="ri-time-line me-1"></i> {{ $pendingCount }} sale(s) waiting for admin approval. Their amounts will enter the wallet after approval.</div>@endif
    <div class="card mb-3"><div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2"><h4 class="card-title mb-0">Sale History</h4><small class="text-muted">Every sale for this party stays here</small></div>
        <div class="card-body"><div class="table-responsive va-mobile-card-table"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Sale</th><th>Products</th><th>Amount</th><th>Received</th><th>Balance</th><th>Recorded by</th><th class="text-end">Actions</th></tr></thead><tbody>
            @forelse($sales as $sale)
            @php $paid = (string) ($sale->paid_total ?? '0'); $due = \App\Support\SaleMoney::decimal(max(0, \App\Support\SaleMoney::paise($sale->total_rupees) - \App\Support\SaleMoney::paise($paid))); @endphp
            <tr><td data-label="Sale"><strong>{{ $sale->invoice_no }}</strong><small class="d-block text-muted">{{ $sale->sale_date->format('d M Y') }}</small><span class="va-status-icon {{ $sale->approval_status === 'approved' ? 'va-status-approved' : 'va-status-pending' }}" title="{{ $sale->approval_status === 'approved' ? 'Approved' : 'Pending approval' }}" aria-label="{{ $sale->approval_status === 'approved' ? 'Approved' : 'Pending approval' }}"><i class="{{ $sale->approval_status === 'approved' ? 'ri-checkbox-circle-line' : 'ri-time-line' }}" aria-hidden="true"></i></span></td>
                <td data-label="Products">@foreach($sale->items as $item)<div>{{ $item->product_name }} · {{ $item->cartons }} CTN × ₹{{ \App\Support\SaleMoney::format($item->rate_rupees) }}</div>@endforeach</td>
                <td data-label="Amount"><strong>₹{{ \App\Support\SaleMoney::format($sale->total_rupees) }}</strong></td>
                <td data-label="Received">₹{{ \App\Support\SaleMoney::format($paid) }}</td>
                <td data-label="Balance">₹{{ \App\Support\SaleMoney::format($due) }}</td>
                <td data-label="Recorded by">{{ $sale->creator?->name ?? 'System' }}@if($sale->referenceUser)<small class="d-block text-muted">Reference: {{ $sale->referenceUser->name }}</small>@endif</td>
                <td data-label="Actions" class="text-end"><div class="d-flex gap-1 justify-content-end flex-wrap">
                    @if(\App\Support\Access::record('sales', 'invoice', $sale))<a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-primary" title="View invoice"><i class="ri-file-list-3-line"></i><span class="visually-hidden">Invoice</span></a>@endif
                    @if(\App\Support\Access::canEdit('sales', $sale))<a href="{{ route('sales.edit', $sale) }}" class="btn btn-sm btn-outline-secondary" title="Edit sale"><i class="ri-pencil-line"></i><span class="visually-hidden">Edit sale</span></a>@endif
                    @if(\App\Support\Access::canDelete('sales', $sale) && \App\Support\SaleMoney::paise($paid) === 0)<form method="POST" action="{{ route('sales.destroy', $sale) }}">@csrf @method('DELETE')<button type="button" class="btn btn-sm btn-outline-danger" data-va-delete data-va-delete-name="{{ $sale->invoice_no }}" title="Delete sale"><i class="ri-delete-bin-line"></i><span class="visually-hidden">Delete sale</span></button></form>@endif
                </div></td>
            </tr>
            @if($sale->approval_status === 'approved' && \App\Support\SaleMoney::paise($due) > 0 && \App\Support\Access::allowed('payments', 'create') && (\App\Support\Access::all('payments') || \App\Support\Access::all('sales') || (int) $sale->user_id === auth()->id()))
            <tr class="va-payment-entry"><td colspan="7"><details><summary><i class="ri-add-circle-line me-1"></i> Record payment for {{ $sale->invoice_no }}</summary>
                <form method="POST" action="{{ route('payments.store') }}" class="row g-2 align-items-end mt-2">@csrf <input type="hidden" name="sale_id" value="{{ $sale->id }}">
                    <div class="col-12 col-sm-6 col-lg-2"><label class="form-label">Date</label><input type="date" name="payment_date" class="form-control" value="{{ now()->toDateString() }}" min="{{ $sale->sale_date->format('Y-m-d') }}" required></div>
                    <div class="col-12 col-sm-6 col-lg-2"><label class="form-label">Amount (₹)</label><input type="number" name="amount_rupees" class="form-control" min="0.01" max="{{ $due }}" step="0.01" required></div>
                    <div class="col-12 col-sm-6 col-lg-2"><label class="form-label">Method</label><select name="method" class="form-select" required><option value="cash">Cash</option><option value="upi">UPI</option><option value="bank">Bank</option><option value="other">Other</option></select></div>
                    <div class="col-12 col-sm-6 col-lg-3"><label class="form-label">Reference</label><input name="reference" class="form-control" maxlength="150" placeholder="Optional"></div>
                    <div class="col-12 col-lg-auto"><button class="btn btn-primary w-100" type="submit">Save Payment</button></div>
                </form></details></td></tr>
            @endif
            @empty <tr><td colspan="7" class="text-muted text-center py-5">No completed sales yet.</td></tr> @endforelse
        </tbody></table></div>{{ $sales->links() }}</div>
    </div>
    <div class="card"><div class="card-header"><h4 class="card-title mb-0">Payment History</h4></div><div class="card-body"><div class="table-responsive va-mobile-card-table"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Date</th><th>Sale</th><th>Amount</th><th>Method / reference</th><th>Recorded by</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
        @forelse($payments as $payment)
        <tr><td data-label="Date">{{ $payment->payment_date->format('d M Y') }}</td><td data-label="Sale">{{ $payment->sale?->invoice_no }}</td><td data-label="Amount"><strong>₹{{ \App\Support\SaleMoney::format($payment->amount_rupees) }}</strong></td><td data-label="Method / reference">{{ strtoupper($payment->method) }}@if($payment->reference)<small class="d-block text-muted">{{ $payment->reference }}</small>@endif</td><td data-label="Recorded by">{{ $payment->creator?->name ?? 'System' }}</td><td data-label="Status"><span class="va-status-icon {{ $payment->approval_status === 'approved' ? 'va-status-approved' : 'va-status-pending' }}" title="{{ $payment->approval_status === 'approved' ? 'Approved' : 'Pending approval' }}" aria-label="{{ $payment->approval_status === 'approved' ? 'Approved' : 'Pending approval' }}"><i class="{{ $payment->approval_status === 'approved' ? 'ri-checkbox-circle-line' : 'ri-time-line' }}" aria-hidden="true"></i></span></td><td data-label="Actions" class="text-end"><div class="d-flex justify-content-end gap-1">@if(\App\Support\Access::canEdit('payments', $payment))<a href="{{ route('payments.edit', $payment) }}" class="btn btn-sm btn-outline-secondary" title="Edit payment"><i class="ri-pencil-line"></i><span class="visually-hidden">Edit payment</span></a>@endif @if(\App\Support\Access::canDelete('payments', $payment))<form method="POST" action="{{ route('payments.destroy', $payment) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="button" data-va-delete data-va-delete-name="Payment #{{ $payment->id }}" title="Delete payment"><i class="ri-delete-bin-line"></i><span class="visually-hidden">Delete payment</span></button></form>@endif</div></td></tr>
        @empty <tr><td colspan="7" class="text-muted text-center py-4">No payments recorded yet.</td></tr> @endforelse
    </tbody></table></div>{{ $payments->links() }}</div></div>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">@endpush
