@extends('layouts.admin')
@section('title', $sale->is_draft ? 'Complete Sale' : (\App\Support\Access::canEdit('sales', $sale) ? 'Edit ' : 'Sale ')
    . $sale->invoice_no)
@section('page-title', $sale->is_draft ? 'Complete Sale' : (\App\Support\Access::canEdit('sales', $sale) ? 'Edit Sale' :
    'Sale Details'))
@section('page-action')<div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary"><i class="ri-arrow-left-line me-1"></i> Sale
            List</a>
        @if (\App\Support\Access::canDelete('sales', $sale) && $payments->isEmpty())
            <form method="POST" action="{{ route('sales.destroy', $sale) }}">@csrf @method('DELETE')<button type="button"
                    class="btn btn-outline-danger" data-va-delete data-va-delete-name="{{ $sale->invoice_no }}">Delete
                    Sale</button></form>
        @endif
    </div>
@endsection
@section('content')
    <div class="va-sales-shell">
        @if (!$sale->is_draft && $sale->approval_status === 'pending')
            <div class="alert alert-warning">This sale is waiting for admin approval. Payment entry opens after approval.
            </div>
        @endif
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">Sale Details</h4>
                <span class="d-flex align-items-center gap-2"><strong>{{ $sale->invoice_no }}</strong>
                    @if ($sale->is_draft)
                        <span class="badge bg-secondary">Unfinished</span>
                    @elseif($sale->approval_status === 'pending')
                    <span class="badge bg-warning text-dark">Pending approval</span>@else<span
                            class="badge bg-success">Approved</span>
                    @endif
                </span>
            </div>
            <div class="card-body">
                <div class="va-sale-profile">
                    <div class="va-sale-profile-info">
                        <small class="text-muted">Party profile</small>
                        <h3 class="mb-1">{{ $sale->customer->name }}</h3>
                        <div><span class="text-muted">Phone:</span> {{ $sale->customer->mobile }}</div>
                        <div><span class="text-muted">Business:</span> {{ $sale->customer->business_name ?: '—' }}</div>
                    </div>
                    <div class="va-sale-profile-wallet">
                        <div><small>Total
                                sales</small><strong>₹{{ \App\Support\SaleMoney::format($wallet['billed']) }}</strong></div>
                        <div><small>Total paid</small><strong
                                class="text-success">₹{{ \App\Support\SaleMoney::format($wallet['paid']) }}</strong></div>
                        <div><small>Balance due</small><strong
                                class="text-danger">₹{{ \App\Support\SaleMoney::format($wallet['balance']) }}</strong></div>
                    </div>
                </div>
                <small class="d-block mt-3 text-muted">This sale: {{ $sale->sale_date->format('d M Y') }} · Added by
                    {{ $sale->creator?->name ?? 'System' }}</small>
            </div>
        </div>

        @if (\App\Support\Access::canEdit('sales', $sale))
            @include('admin.sales.partials.form')
        @else
            <div class="card mb-3">
                <div class="card-header">
                    <h4 class="card-title mb-0">Products</h4>
                </div>
                <div class="card-body">
                    @foreach ($sale->items as $item)
                        <div class="va-sale-readonly-row">
                            <div><small>Product</small><strong>{{ $item->product_name }}</strong></div>
                            <div><small>Qty</small><strong>{{ $item->cartons }} CTN</strong></div>
                            <div>
                                <small>Rate</small><strong>₹{{ \App\Support\SaleMoney::format($item->rate_rupees) }}</strong>
                            </div>
                            <div>
                                <small>Discount</small><strong>₹{{ \App\Support\SaleMoney::format($item->discount_rupees) }}</strong>
                            </div>
                            <div><small>Reference</small><strong>{{ $item->referenceUser?->name ?? '—' }}</strong></div>
                            <div>
                                <small>Amount</small><strong>₹{{ \App\Support\SaleMoney::format($item->line_total_rupees) }}</strong>
                            </div>
                        </div>
                    @endforeach
                    <div class="va-sale-totals mt-3">
                        <div><span>Sale
                                total</span><strong>₹{{ \App\Support\SaleMoney::format($sale->total_rupees) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card" id="payments">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">Payments · Credit / Debit</h4><small class="text-muted">Only this
                    invoice</small>
            </div>
            <div class="card-body">
                <div class="va-sale-profile-wallet va-sale-invoice-wallet mb-3">
                    <div><small>Invoice
                            total</small><strong>₹{{ \App\Support\SaleMoney::format($sale->total_rupees) }}</strong></div>
                    <div><small>Net received</small><strong
                            class="text-success">₹{{ \App\Support\SaleMoney::format($paid) }}</strong></div>
                    <div><small>Due</small><strong class="text-danger">₹{{ \App\Support\SaleMoney::format($due) }}</strong>
                    </div>
                </div>
                <p class="small text-muted">Credit = payment received from party. Debit = amount returned to party. Pending
                    entries affect the balance after admin approval.</p>
                @if ($sale->is_draft || $sale->approval_status !== 'approved')
                    <p class="text-muted">Save the sale and get approval before recording a payment.</p>
                @elseif(
                    \App\Support\Access::allowed('payments', 'create') &&
                        (\App\Support\Access::all('payments') ||
                            \App\Support\Access::all('sales') ||
                            (int) $sale->user_id === auth()->id()))
                    <form method="POST" action="{{ route('payments.store') }}"
                        class="row g-2 align-items-end va-payment-form mb-4">
                        @csrf <input type="hidden" name="sale_id" value="{{ $sale->id }}">
                        <div class="col-12 col-sm-6 col-lg-2"><label class="form-label">Type <span
                                    class="text-danger">*</span></label><select name="entry_type" class="form-select"
                                required>
                                <option value="credit" @selected(old('entry_type') === 'credit')>Credit (received)</option>
                                <option value="debit" @selected(old('entry_type') === 'debit')>Debit (returned)</option>
                            </select></div>
                        <div class="col-12 col-sm-6 col-lg-2"><label class="form-label">Date <span
                                    class="text-danger">*</span></label><input type="date" name="payment_date"
                                class="form-control" value="{{ old('payment_date', now()->toDateString()) }}"
                                min="{{ $sale->sale_date->format('Y-m-d') }}" required></div>
                        <div class="col-12 col-sm-6 col-lg-2"><label class="form-label">Amount (₹) <span
                                    class="text-danger">*</span></label><input type="number" name="amount_rupees"
                                class="form-control" min="0.01" step="0.01" value="{{ old('amount_rupees') }}"
                                required>
                            @error('amount_rupees')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2"><label class="form-label">Method <span
                                    class="text-danger">*</span></label><select name="method" class="form-select" required>
                                @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank', 'other' => 'Other'] as $key => $label)
                                    <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2"><label class="form-label">Reference</label><input
                                name="reference" class="form-control" maxlength="150" value="{{ old('reference') }}"
                                placeholder="Optional"></div>
                        <div class="col-12 col-sm-6 col-lg-2"><button type="submit" class="btn btn-primary w-100">Save
                                Entry</button></div>
                    </form>
                @endif
                <h5 class="mb-3">Payment history</h5>
                <div class="table-responsive va-payment-list">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Method / reference</th>
                                <th>Credit</th>
                                <th>Debit</th>
                                <th>Added by</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $payment)
                                <tr>
                                    <td data-label="Date">{{ $payment->payment_date->format('d M Y') }}</td>
                                    <td data-label="Type"><span
                                            class="badge {{ $payment->entry_type === 'debit' ? 'bg-danger' : 'bg-success' }}">{{ $payment->entry_type === 'debit' ? 'Debit' : 'Credit' }}</span>
                                    </td>
                                    <td data-label="Method">{{ strtoupper($payment->method) }}@if ($payment->reference)
                                            <small class="d-block text-muted">{{ $payment->reference }}</small>
                                        @endif
                                    </td>
                                    <td data-label="Credit">
                                        {{ $payment->entry_type === 'credit' ? '₹' . \App\Support\SaleMoney::format($payment->amount_rupees) : '—' }}
                                    </td>
                                    <td data-label="Debit">
                                        {{ $payment->entry_type === 'debit' ? '₹' . \App\Support\SaleMoney::format($payment->amount_rupees) : '—' }}
                                    </td>
                                    <td data-label="Added by">{{ $payment->creator?->name ?? 'System' }}</td>
                                    <td data-label="Status">{{ ucfirst($payment->approval_status) }}</td>
                                    <td data-label="Action" class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if (\App\Support\Access::canEdit('payments', $payment))
                                                <a href="{{ route('payments.edit', $payment) }}"
                                                    class="btn btn-sm btn-outline-secondary">Edit</a>
                                                @endif @if (\App\Support\Access::canDelete('payments', $payment))
                                                    <form method="POST"
                                                        action="{{ route('payments.destroy', $payment) }}">@csrf
                                                        @method('DELETE')<button type="button"
                                                            class="btn btn-sm btn-outline-danger" data-va-delete
                                                            data-va-delete-name="Payment #{{ $payment->id }}">Delete</button>
                                                    </form>
                                                @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No payment entries yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-3" id="previous-sales">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="card-title mb-0">Previous sales ({{ $previousSales->total() }})</h4>
                    <small class="text-muted">Same party · view only here</small>
                </div>
                <div class="card-body">
                    @forelse($previousSales as $oldSale)
                        @php
                            $oldPaid = $oldSale->payments
                                ->where('approval_status', 'approved')
                                ->sum(
                                    fn($entry) => ($entry->entry_type === 'debit' ? -1 : 1) *
                                        \App\Support\SaleMoney::paise($entry->amount_rupees),
                                );
                            $oldDue = max(0, \App\Support\SaleMoney::paise($oldSale->total_rupees) - $oldPaid);
                        @endphp
                        <section class="va-sale-history-entry" aria-label="Sale {{ $oldSale->invoice_no }}">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                    <h5 class="mb-1">{{ $oldSale->invoice_no }} <span
                                            class="badge {{ $oldSale->approval_status === 'approved' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($oldSale->approval_status) }}</span>
                                    </h5>
                                    <small class="text-muted">{{ $oldSale->sale_date->format('d M Y') }} · Added by
                                        {{ $oldSale->creator?->name ?? 'System' }}</small>
                                </div>
                                <a href="{{ route('sales.edit', $oldSale) }}"
                                    class="btn btn-sm btn-outline-primary">{{ \App\Support\Access::canEdit('sales', $oldSale) ? 'Edit this sale' : 'View this sale' }}</a>
                            </div>
                            @foreach ($oldSale->items as $item)
                                <div class="va-sale-readonly-row">
                                    <div><small>Product</small><strong>{{ $item->product_name }}</strong></div>
                                    <div><small>Qty</small><strong>{{ $item->cartons }} CTN</strong></div>
                                    <div>
                                        <small>Rate</small><strong>₹{{ \App\Support\SaleMoney::format($item->rate_rupees) }}</strong>
                                    </div>
                                    <div>
                                        <small>Discount</small><strong>₹{{ \App\Support\SaleMoney::format($item->discount_rupees) }}</strong>
                                    </div>
                                    <div><small>Reference</small><strong>{{ $item->referenceUser?->name ?? '—' }}</strong>
                                    </div>
                                    <div>
                                        <small>Amount</small><strong>₹{{ \App\Support\SaleMoney::format($item->line_total_rupees) }}</strong>
                                    </div>
                                </div>
                            @endforeach
                            @if (
                                \App\Support\SaleMoney::paise($oldSale->discount_rupees) ||
                                    \App\Support\SaleMoney::paise($oldSale->vehicle_charge_rupees))
                                <small class="d-block mt-2 text-muted">Invoice discount
                                    ₹{{ \App\Support\SaleMoney::format($oldSale->discount_rupees) }} · Vehicle charge
                                    ₹{{ \App\Support\SaleMoney::format($oldSale->vehicle_charge_rupees) }}</small>
                            @endif
                            @if ($oldSale->reference)
                                <small class="d-block mt-2 text-muted">Sale reference: {{ $oldSale->reference }}</small>
                            @endif
                            @if ($oldSale->due_date)
                                <small class="d-block text-muted">Due date: {{ $oldSale->due_date->format('d M Y') }}</small>
                            @endif
                            <div class="va-sale-history-totals mt-2"><span>Total
                                    ₹{{ \App\Support\SaleMoney::format($oldSale->total_rupees) }}</span><span
                                    class="text-success">Paid
                                    ₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($oldPaid)) }}</span><span
                                    class="text-danger">Due
                                    ₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($oldDue)) }}</span>
                            </div>
                            <details class="va-sale-history-payments mt-2">
                                <summary>Payment history ({{ $oldSale->payments->count() }})</summary>
                                @forelse($oldSale->payments->sortByDesc('payment_date') as $entry)
                                    <div class="va-history-line"><span>{{ $entry->payment_date->format('d M Y') }} ·
                                            {{ ucfirst($entry->entry_type) }} · {{ strtoupper($entry->method) }}@if ($entry->reference)
                                                · {{ $entry->reference }}
                                            @endif <small
                                                class="text-muted">({{ ucfirst($entry->approval_status) }},
                                                {{ $entry->creator?->name ?? 'System' }})</small></span><span
                                            class="{{ $entry->entry_type === 'credit' ? 'text-success' : 'text-danger' }}">₹{{ \App\Support\SaleMoney::format($entry->amount_rupees) }}</span>
                                    </div>
                                @empty <p class="small text-muted mb-0">No payment entries yet.</p>
                                @endforelse
                            </details>
                        </section>
                    @empty
                        <p class="text-muted mb-0">This party has no previous sales yet.</p>
                    @endforelse
                    @if ($previousSales->hasPages())
                        <div class="mt-3">{{ $previousSales->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endsection
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/js/sales-form.js') }}" defer></script>
    @endpush
