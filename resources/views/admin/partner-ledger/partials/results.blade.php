<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="va-ledger-metric"><small>Entries</small><strong>{{ \App\Support\CartonNumber::format($summary['count']) }}</strong></div></div>
    <div class="col-6 col-lg-3"><div class="va-ledger-metric"><small>Sent</small><strong>₹{{ \App\Support\RupeeAmount::format($summary['sent']) }}</strong></div></div>
    <div class="col-6 col-lg-3"><div class="va-ledger-metric"><small>Received</small><strong>₹{{ \App\Support\RupeeAmount::format($summary['received']) }}</strong></div></div>
    <div class="col-6 col-lg-3"><div class="va-ledger-metric"><small>{{ $summary['balance']['label'] }}</small><strong>₹{{ \App\Support\RupeeAmount::format($summary['balance']['amount']) }}</strong></div></div>
</div>
<div class="table-responsive"><table class="table align-middle mb-0">
    <thead class="table-light"><tr><th>Date</th><th>Partner</th><th>Type</th><th>Amount</th><th>Note</th><th>Recorded by</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    @forelse($entries as $entry)
        <tr><td>{{ $entry->transaction_date->format('d M Y') }}</td>
            <td><a href="{{ route('partners.show', $entry->partner) }}">{{ $entry->partner->name }}</a></td>
            <td><span class="badge {{ $entry->type === 'send' ? 'bg-warning text-dark' : 'bg-success' }}">{{ ucfirst($entry->type) }}</span></td>
            <td><strong>₹{{ \App\Support\RupeeAmount::format($entry->amount_rupees) }}</strong></td>
            <td class="va-ledger-note" title="{{ $entry->note }}">{{ $entry->note ?: '—' }}</td>
            <td>{{ $entry->creator?->name ?? 'System' }}@if($entry->updated_by)<br><small class="text-muted">Edited by {{ $entry->editor?->name ?? 'System' }}</small>@endif</td>
            <td class="text-end text-nowrap"><a href="{{ route('partner-ledger.edit', $entry) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                <form method="POST" action="{{ route('partner-ledger.destroy', $entry) }}" class="d-inline">@csrf @method('DELETE')
                    <button type="button" class="btn btn-sm btn-outline-danger" data-va-delete data-va-delete-name="{{ $entry->partner->name }} · ₹{{ \App\Support\RupeeAmount::format($entry->amount_rupees) }}">Delete</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted py-5">No partner transactions found.</td></tr>
    @endforelse
    </tbody>
</table></div>
@include('shared.pagination', ['paginator' => $entries, 'label' => 'Partner transactions pagination'])
