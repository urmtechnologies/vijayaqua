@extends('layouts.admin')

@section('title', 'Stock Entry #'.$entry->id)
@section('page-title', 'Stock Entry #'.$entry->id)
@section('page-action')
    <div class="d-flex gap-2">
        @if(\App\Support\Access::canEdit('stock-entries', $entry))<a href="{{ route('stock-entries.edit', $entry) }}" class="btn btn-primary">Edit Entry</a>@endif
        @if(\App\Support\Access::canDelete('stock-entries', $entry))<form method="POST" action="{{ route('stock-entries.destroy', $entry) }}" class="d-inline">@csrf @method('DELETE')
            <button type="button" class="btn btn-outline-danger" data-va-delete data-va-delete-name="Stock Entry #{{ $entry->id }}">Delete</button>
        </form>@endif
        <a href="{{ route('stock-entries.index') }}" class="btn btn-outline-secondary">Back to List</a>
    </div>
@endsection

@section('content')
<p>@include('shared.approval-status', ['record' => $entry])</p>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="card-title mb-0">Entry Details</h4>
        <span class="text-muted">{{ $entry->entry_date->format('d M Y') }}</span>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Added by <strong class="text-body">{{ $entry->creator?->name ?? 'System' }}</strong> · {{ $entry->created_at->format('d M Y, h:i A') }}
            @if($entry->updated_by) · Updated by <strong class="text-body">{{ $entry->editor?->name ?? 'System' }}</strong> @endif
        </p>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Product</th><th>Type</th><th class="text-end">Quantity (CTN)</th></tr></thead>
                <tbody>
                    @foreach($entry->items as $item)
                        <tr><td>{{ $item->product?->name ?? 'Product unavailable' }}</td><td>{{ $item->type ? ucfirst($item->type) : 'Not set' }}</td><td class="text-end">{{ \App\Support\CartonNumber::format($item->cartons) }} CTN</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th colspan="2">Total</th><th class="text-end">{{ \App\Support\CartonNumber::format($entry->items->sum('cartons')) }} CTN</th></tr></tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
