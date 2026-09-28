@extends('layouts.admin')
@section('title', 'Upcoming Order #'.$order->id)
@section('page-title', 'Upcoming Order #'.$order->id)
@section('page-action')<div class="d-flex gap-2">
    @if(\App\Support\Access::canEdit('upcoming-orders', $order))<a href="{{ route('upcoming-orders.edit', $order) }}" class="btn btn-primary">Edit Order</a>@endif
    @if(\App\Support\Access::canDelete('upcoming-orders', $order))<form method="POST" action="{{ route('upcoming-orders.destroy', $order) }}" class="d-inline">@csrf @method('DELETE')<button type="button" class="btn btn-outline-danger" data-va-delete data-va-delete-name="Upcoming Order #{{ $order->id }}">Delete</button></form>@endif
    <a href="{{ route('upcoming-orders.index') }}" class="btn btn-outline-secondary">Back to List</a>
</div>@endsection
@section('content')
<p>@include('shared.approval-status', ['record' => $order])</p>
<div class="card">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2"><h4 class="card-title mb-0">Order Details</h4><span class="text-muted">{{ $order->scheduled_date->format('d M Y') }}</span></div>
    <div class="card-body">
        <div class="row g-3 mb-4"><div class="col-md-6"><small class="text-muted">Customer</small><h5 class="mb-1">{{ $order->customer->name }}</h5><div>{{ $order->customer->mobile }}</div></div>
            <div class="col-md-6 text-md-end"><small class="text-muted">Recorded by</small><div>{{ $order->creator?->name ?? 'System' }}</div>@if($order->updated_by)<small class="text-muted">Edited by {{ $order->editor?->name ?? 'System' }}</small>@endif</div></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Product</th><th class="text-end">Qty (CTN)</th></tr></thead><tbody>
            @foreach($order->items as $item)<tr><td>{{ $item->product_name }}</td><td class="text-end">{{ \App\Support\CartonNumber::format($item->cartons) }}</td></tr>@endforeach
            </tbody><tfoot><tr><th>Total</th><th class="text-end">{{ \App\Support\CartonNumber::format($order->items->sum('cartons')) }} CTN</th></tr></tfoot></table></div>
        @if($order->note)<div class="mt-4"><strong>Note</strong><p class="mb-0 text-muted va-prewrap">{{ $order->note }}</p></div>@endif
        <div class="alert alert-info mt-4 mb-0">This is a planned order. Stock is deducted only when a sale is recorded.</div>
    </div>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/operations.css') }}">@endpush
