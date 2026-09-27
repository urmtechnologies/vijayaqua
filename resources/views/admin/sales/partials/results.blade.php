<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="va-ledger-metric"><small>Invoices</small><strong>{{ \App\Support\CartonNumber::format($summary['invoices']) }}</strong></div></div>
    <div class="col-6 col-lg-3"><div class="va-ledger-metric"><small>Total billed</small><strong>₹{{ \App\Support\RupeeAmount::format($summary['total']) }}</strong></div></div>
    <div class="col-6 col-lg-3"><div class="va-ledger-metric"><small>Received</small><strong>₹{{ \App\Support\RupeeAmount::format($summary['paid']) }}</strong></div></div>
    <div class="col-6 col-lg-3"><div class="va-ledger-metric"><small>Due</small><strong>₹{{ \App\Support\RupeeAmount::format($summary['due']) }}</strong></div></div>
</div>
<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Invoice</th><th>Party</th><th>Date</th><th>Total</th><th>Due</th><th>Added by</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($sales as $sale)
            @php $due = (int) $sale->total_rupees - (int) ($sale->paid_total ?? 0); @endphp
            <tr>
                <td><strong>{{ $sale->invoice_no }}</strong></td>
                <td><a href="{{ route('customers.show', $sale->customer) }}">{{ $sale->customer->name }}</a><br><small class="text-muted">{{ $sale->customer->mobile }}</small></td>
                <td>{{ $sale->sale_date->format('d M Y') }}</td>
                <td>₹{{ \App\Support\RupeeAmount::format($sale->total_rupees) }}</td>
                <td><span class="badge {{ $due > 0 ? 'bg-warning text-dark' : 'bg-success' }}">{{ $due > 0 ? '₹'.\App\Support\RupeeAmount::format($due) : 'Paid' }}</span></td>
                <td>{{ $sale->creator?->name ?? 'System' }}</td>
                <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('sales.show', $sale) }}">Invoice</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-5">No invoices found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('shared.pagination', ['paginator' => $sales, 'label' => 'Sales pagination'])
