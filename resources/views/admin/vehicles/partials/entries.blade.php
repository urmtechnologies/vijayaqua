<div class="va-vehicle-feedback alert" role="status" hidden></div>
<div class="va-vehicle-list">
    @forelse($entries as $entry)
    <article class="va-vehicle-list-entry">
        <div class="va-vehicle-list-person"><strong>{{ $entry->creator?->name ?? 'System' }}</strong><small>{{ $entry->creator?->mobile }}</small></div>
        <div class="va-vehicle-list-route"><small>{{ $entry->entry_date->format('d M Y') }} · #{{ $entry->id }}</small><strong>{{ $entry->from_destination }} → {{ $entry->to_destination }}</strong>
            <small>@foreach($entry->items as $item){{ $item->product_name }} × {{ $item->cartons }} CTN{{ ! $loop->last ? ' · ' : '' }}@endforeach</small>
            <small>Reference: {{ $entry->referenceUser?->name ?? 'Deleted user' }}</small>
        </div>
        <strong class="va-vehicle-list-amount">₹{{ \App\Support\SaleMoney::format($entry->amount_rupees) }}</strong>
        <div>@include('shared.approval-status', ['record' => $entry])</div>
        <div class="va-vehicle-list-actions">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('vehicle-entries.account', $entry->user_id) }}">View</a>
            @if(auth()->user()->role === 'admin' && $entry->approval_status === 'pending')
                <form method="POST" action="{{ route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]) }}" data-vehicle-approve>@csrf<button class="btn btn-sm btn-success">Approve</button></form>
            @endif
        </div>
    </article>
    @empty
        <p class="text-muted text-center py-5 mb-0">No vehicle entries found.</p>
    @endforelse
</div>
@include('shared.pagination', ['paginator' => $entries, 'label' => 'Vehicle entries pagination'])
