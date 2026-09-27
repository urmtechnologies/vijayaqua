@extends('layouts.admin')

@section('title', 'Stock Entry #'.$entry->id)
@section('page-title', 'Stock Entry #'.$entry->id)
@section('page-action')
    <div class="d-flex gap-2">
        <a href="{{ route('stock-entries.edit', $entry) }}" class="btn btn-primary">Edit Entry</a>
        <a href="{{ route('stock-entries.index') }}" class="btn btn-outline-secondary">Back to List</a>
    </div>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="card-title mb-0">Entry Details</h4>
        <span class="text-muted">{{ $entry->entry_date->format('d M Y') }}</span>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Added by <strong class="text-body">{{ $entry->creator?->name ?? 'System' }}</strong> · {{ $entry->created_at->format('d M Y, h:i A') }}</p>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Product</th><th class="text-end">Quantity (CTN)</th></tr></thead>
                <tbody>
                    @foreach($entry->items as $item)
                        <tr><td>{{ $item->product?->name ?? 'Product unavailable' }}</td><td class="text-end">{{ \App\Support\CartonNumber::format($item->cartons) }} CTN</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th>Total</th><th class="text-end">{{ \App\Support\CartonNumber::format($entry->items->sum('cartons')) }} CTN</th></tr></tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
