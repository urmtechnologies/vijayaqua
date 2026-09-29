@extends('layouts.admin')
@section('title', $customer->name . ' · Sales')
@section('page-title', 'Party Sales')
@section('page-action')<a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">
    <i class="ri-arrow-left-line me-1"></i> Sales</a>@endsection
@section('content')
    <div class="va-sales-shell">
        <div class="card mb-3">
            <div class="card-body va-sale-profile">
                <div class="va-sale-profile-info"><small class="text-muted">Party profile</small>
                    <h3 class="mb-1">{{ $customer->name }}</h3>
                    <div><span class="text-muted">Phone:</span> {{ $customer->mobile }}</div>
                    <div><span class="text-muted">Business:</span> {{ $customer->business_name ?: '—' }}</div>
                </div>
                <div class="va-sale-profile-wallet">
                    <div><small>Total sales</small><strong>₹{{ \App\Support\SaleMoney::format($wallet['billed']) }}</strong>
                    </div>
                    <div><small>Total received</small><strong
                            class="text-success">₹{{ \App\Support\SaleMoney::format($wallet['paid']) }}</strong></div>
                    <div><small>Balance due</small><strong
                            class="text-danger">₹{{ \App\Support\SaleMoney::format($wallet['balance']) }}</strong></div>
                </div>
            </div>
        </div>
        @if ($pendingCount)
            <div class="alert alert-warning">{{ $pendingCount }} sale(s) waiting for admin approval. Their amounts enter the
                balance after approval.</div>
        @endif

        <div class="card mb-3">
            <div class="card-header">
                <h4 class="card-title mb-0">Sales ({{ $sales->total() }})</h4>

            </div>
            <div class="card-body">
                @forelse($sales as $sale)
                    @php
                        $received =
                            \App\Support\SaleMoney::paise((string) ($sale->credit_total ?? 0)) -
                            \App\Support\SaleMoney::paise((string) ($sale->debit_total ?? 0));
                        $due = max(0, \App\Support\SaleMoney::paise($sale->total_rupees) - $received);
                    @endphp
                    <section class="va-sale-history-entry" aria-label="Sale {{ $sale->invoice_no }}">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <h5 class="mb-1">{{ $sale->invoice_no }} <span
                                        class="badge {{ $sale->approval_status === 'approved' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $sale->approval_status === 'approved' ? 'Approved' : 'Pending approval' }}</span>
                                </h5><small class="text-muted">{{ $sale->sale_date->format('d M Y') }} · Added by
                                    {{ $sale->creator?->name ?? 'System' }}</small>
                            </div>
                            @if (auth()->user()->role === 'admin')
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                    data-bs-target="#editSaleModal"
                                    data-sale-url="{{ route('sales.edit', $sale) }}?popup=1">Edit sale</button>
                            @endif
                        </div>
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
                                <div><small>Reference</small><strong>{{ $item->referenceUser?->name ?? '—' }}</strong>
                                </div>
                                <div>
                                    <small>Amount</small><strong>₹{{ \App\Support\SaleMoney::format($item->line_total_rupees) }}</strong>
                                </div>
                            </div>
                        @endforeach
                        @if (
                            \App\Support\SaleMoney::paise($sale->discount_rupees) ||
                                \App\Support\SaleMoney::paise($sale->vehicle_charge_rupees))
                            <small class="d-block mt-2 text-muted">Invoice discount
                                ₹{{ \App\Support\SaleMoney::format($sale->discount_rupees) }} · Vehicle charge
                                ₹{{ \App\Support\SaleMoney::format($sale->vehicle_charge_rupees) }}</small>
                        @endif
                        <div class="va-sale-history-totals mt-3"><span>Total
                                ₹{{ \App\Support\SaleMoney::format($sale->total_rupees) }}</span><span
                                class="text-success">Received
                                ₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($received)) }}</span><span
                                class="text-danger">Due
                                ₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($due)) }}</span></div>
                    </section>
                @empty <p class="text-muted mb-0">No completed sales yet. Add products below to create the first sale.
                    </p>
                @endforelse
                @if ($sales->hasPages())
                    <div class="mt-3">{{ $sales->links() }}</div>
                @endif
            </div>
        </div>

        @if (\App\Support\Access::allowed('sales', 'create'))
            @if ($draft && \App\Support\Access::canEdit('sales', $draft))
                @include('admin.sales.partials.form', ['sale' => $draft])
            @else
                <form method="POST" action="{{ route('sales.store') }}" class="text-center mb-3">@csrf
                    <input type="hidden" name="party_name" value="{{ $customer->name }}"><input type="hidden"
                        name="mobile" value="{{ $customer->mobile }}"><input type="hidden" name="business_name"
                        value="{{ $customer->business_name }}"><input type="hidden" name="sale_date"
                        value="{{ now()->toDateString() }}">
                    <button type="submit" class="btn btn-primary va-add-product-round" aria-label="Add new sale"
                        title="Add new sale"><i class="ri-add-line" aria-hidden="true"></i></button>
                    <div class="small text-muted mt-1">Add new sale</div>
                </form>
            @endif
        @endif

        <div class="card mb-3" id="payments">
            <div class="card-header">
                <h4 class="card-title mb-0">Credit / Debit</h4>
            </div>
            <div class="card-body">

                @if (\App\Support\Access::allowed('payments', 'create'))
                    @if ($payableSales->isNotEmpty())
                        <form method="POST" action="{{ route('payments.store') }}" class="row g-2 align-items-end mb-3">
                            @csrf
                            <div class="col-12 col-md-4 col-lg-2"><label class="form-label">Sale *</label><select
                                    name="sale_id" class="form-select" required>
                                    <option value="">Select sale</option>
                                    @foreach ($payableSales as $entry)
                                        <option value="{{ $entry->id }}" @selected((string) old('sale_id') === (string) $entry->id)>
                                            {{ $entry->invoice_no }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2"><label class="form-label">Type *</label><select
                                    name="entry_type" class="form-select">
                                    <option value="credit" @selected(old('entry_type') === 'credit')>Credit (received)</option>
                                    <option value="debit" @selected(old('entry_type') === 'debit')>Debit (returned)</option>
                                </select></div>
                            <div class="col-6 col-md-4 col-lg-2"><label class="form-label">Date *</label><input
                                    type="date" name="payment_date" class="form-control"
                                    value="{{ old('payment_date', now()->toDateString()) }}" required></div>
                            <div class="col-6 col-md-4 col-lg-2"><label class="form-label">Amount (₹) *</label><input
                                    type="number" name="amount_rupees" class="form-control" min="0.01" step="0.01"
                                    value="{{ old('amount_rupees') }}" required></div>
                            <div class="col-6 col-md-4 col-lg-2"><label class="form-label">Method *</label><select
                                    name="method" class="form-select">
                                    <option value="cash">Cash</option>
                                    <option value="upi">UPI</option>
                                    <option value="bank">Bank</option>
                                    <option value="other">Other</option>
                                </select></div>
                            <div class="col-6 col-md-4 col-lg-2"><label class="form-label">Reference</label><input
                                    name="reference" class="form-control" maxlength="150"
                                    value="{{ old('reference') }}"></div>
                            <div class="col-12 text-end"><button class="btn btn-primary" type="submit">Save
                                    Entry</button></div>
                        </form>
                    @else
                        <p class="text-muted">No History</p>
                    @endif
                @endif
            </div>
        </div>

        <div class="card" id="payment-history">
            <div class="card-header">
                <h4 class="card-title mb-0">Payment History</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive va-mobile-card-table">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Sale</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Method / reference</th>
                                <th>Added by</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $payment)
                                <tr>
                                    <td data-label="Date">{{ $payment->payment_date->format('d M Y') }}</td>
                                    <td data-label="Sale">{{ $payment->sale?->invoice_no }}</td>
                                    <td data-label="Type">{{ ucfirst($payment->entry_type) }}</td>
                                    <td data-label="Amount"
                                        class="{{ $payment->entry_type === 'credit' ? 'text-success' : 'text-danger' }}">
                                        ₹{{ \App\Support\SaleMoney::format($payment->amount_rupees) }}</td>
                                    <td data-label="Method / reference">{{ strtoupper($payment->method) }}@if ($payment->reference)
                                            · {{ $payment->reference }}
                                        @endif
                                    </td>
                                    <td data-label="Added by">{{ $payment->creator?->name ?? 'System' }}</td>
                                    <td data-label="Status">{{ ucfirst($payment->approval_status) }}</td>
                                </tr>
                            @empty <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No payments recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($payments->hasPages())
                    <div class="mt-3">{{ $payments->links() }}</div>
                @endif
            </div>
        </div>
    </div>
    @if (auth()->user()->role === 'admin')
        <div class="modal fade" id="editSaleModal" tabindex="-1" aria-label="Edit sale">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit sale</h5><button type="button" class="btn-close"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0"><iframe title="Edit sale form" class="va-sale-edit-frame"
                            data-party-path="{{ route('customers.show', $customer) }}"></iframe></div>
                </div>
            </div>
        </div>
    @endif
@endsection
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">
@endpush
@push('scripts')
    <script src="{{ asset('assets/js/sales-form.js') }}" defer></script>
    <script src="{{ asset('assets/js/sale-popup.js') }}" defer></script>
@endpush
