<div class="row g-3 mb-4">
    <div class="col-sm-6"><div class="va-ledger-metric"><small>Matching payments</small><strong>{{ \App\Support\CartonNumber::format($summary['count']) }}</strong></div></div>
    <div class="col-sm-6"><div class="va-ledger-metric"><small>Approved received</small><strong>₹{{ \App\Support\RupeeAmount::format($summary['received']) }}</strong></div></div>
</div>
<div class="table-responsive"><table class="table align-middle mb-0">
    <thead class="table-light"><tr><th>Date</th><th>Invoice</th><th>Party</th><th>Type</th><th>Amount</th><th>Method</th><th>Reference</th><th>Added by</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    @forelse($payments as $payment)
        <tr>
            <td>{{ $payment->payment_date->format('d M Y') }}</td>
            <td>@if(\App\Support\Access::allowed('sales', 'invoice') && \App\Support\Access::record('sales', 'invoice', $payment->sale))<a href="{{ route('sales.show', $payment->sale) }}">{{ $payment->sale->invoice_no }}</a>@else {{ $payment->sale->invoice_no }} @endif</td>
            <td>{{ $payment->sale->customer->name }}<br><small class="text-muted">{{ $payment->sale->customer->mobile }}</small></td>
            <td>{{ ucfirst($payment->entry_type) }}</td>
            <td><strong>₹{{ \App\Support\RupeeAmount::format($payment->amount_rupees) }}</strong> @include('admin.sales.partials.status-icon', ['record' => $payment])</td>
            <td>{{ strtoupper($payment->method) }}</td>
            <td>{{ $payment->reference ?: '—' }}</td>
            <td>{{ $payment->creator?->name ?? 'System' }}@if($payment->editor)<br><small class="text-muted">Edited by {{ $payment->editor->name }}</small>@endif</td>
            <td class="text-end text-nowrap">@if(\App\Support\Access::canEdit('payments', $payment))<a href="{{ route('payments.edit', $payment) }}" class="btn btn-sm btn-outline-secondary">Edit</a>@endif
                @if(\App\Support\Access::canDelete('payments', $payment))<form method="POST" action="{{ route('payments.destroy', $payment) }}" class="d-inline">@csrf @method('DELETE')<button type="button" class="btn btn-sm btn-outline-danger" data-va-delete data-va-delete-name="Payment #{{ $payment->id }}">Delete</button></form>@endif</td>
        </tr>
    @empty
        <tr><td colspan="9" class="text-center text-muted py-5">No payments found.</td></tr>
    @endforelse
    </tbody>
</table></div>
@include('shared.pagination', ['paginator' => $payments, 'label' => 'Payments pagination'])
