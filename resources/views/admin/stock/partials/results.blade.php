<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <div class="va-stock-metric"><small>Matching entries</small><strong>{{ \App\Support\CartonNumber::format($summary['entries']) }}</strong></div>
    </div>
    <div class="col-sm-6">
        <div class="va-stock-metric"><small>Matching CTN</small><strong>{{ \App\Support\CartonNumber::format($summary['cartons']) }}</strong></div>
    </div>
</div>
<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead class="table-light"><tr>
            <th>Entry</th><th>Date</th><th>Type</th><th>Products</th><th>Total CTN</th><th>Added by</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse($entries as $entry)
            <tr>
                <td><strong>#{{ $entry->id }}</strong></td>
                <td>{{ $entry->entry_date->format('d M Y') }}</td>
                <td>{{ $entry->items->pluck('type')->filter()->unique()->map(fn ($type) => ucfirst($type))->join(', ') ?: 'Not set' }}</td>
                <td>{{ $entry->items_count }}</td>
                <td><strong>{{ \App\Support\CartonNumber::format($entry->carton_total) }}</strong> CTN</td>
                <td>{{ $entry->creator?->name ?? 'System' }}@if($entry->updated_by)<br><small class="text-muted">Edited by {{ $entry->editor?->name ?? 'System' }}</small>@endif</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('stock-entries.show', $entry) }}">View</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('stock-entries.edit', $entry) }}">Edit</a>
                    <form method="POST" action="{{ route('stock-entries.destroy', $entry) }}" class="d-inline">@csrf @method('DELETE')
                        <button type="button" class="btn btn-sm btn-outline-danger" data-va-delete data-va-delete-name="Stock Entry #{{ $entry->id }}">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-5">No stock entries found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('shared.pagination', ['paginator' => $entries, 'label' => 'Stock entries pagination'])
