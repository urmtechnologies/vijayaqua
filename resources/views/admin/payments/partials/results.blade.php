<div class="row g-3 mb-4">
    <div class="col-sm-6"><div class="va-ledger-metric"><small>Matching payments</small><strong>{{ \App\Support\CartonNumber::format($summary['count']) }}</strong></div></div>
    <div class="col-sm-6"><div class="va-ledger-metric"><small>Total received</small><strong>₹{{ \App\Support\RupeeAmount::format($summary['received']) }}</strong></div></div>
</div>
<div class="table-responsive"><table class="table align-middle mb-0">
    <thead class="table-light"><tr><th>Date</th><th>Invoice</th><th>Party</th><th>Amount</th><th>Method</th><th>Reference</th><th>Added by</th></tr></thead>
    <tbody>
    @forelse($payments as $payment)
        <tr>
            <td>{{ $payment->payment_date->format('d M Y') }}</td>
            <td><a href="{{ route('sales.show', $payment->sale) }}">{{ $payment->sale->invoice_no }}</a></td>
            <td>{{ $payment->sale->customer->name }}<br><small class="text-muted">{{ $payment->sale->customer->mobile }}</small></td>
            <td><strong>₹{{ \App\Support\RupeeAmount::format($payment->amount_rupees) }}</strong></td>
            <td>{{ strtoupper($payment->method) }}</td>
            <td>{{ $payment->reference ?: '—' }}</td>
            <td>{{ $payment->creator?->name ?? 'System' }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted py-5">No payments found.</td></tr>
    @endforelse
    </tbody>
</table></div>
@include('shared.pagination', ['paginator' => $payments, 'label' => 'Payments pagination'])
