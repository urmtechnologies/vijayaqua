<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="va-stock-metric"><small>Products</small><strong>{{ \App\Support\CartonNumber::format($summary->products) }}</strong></div></div>
    <div class="col-6 col-lg-3"><div class="va-stock-metric"><small>Received CTN</small><strong>{{ \App\Support\CartonNumber::format($summary->received) }}</strong></div></div>
    <div class="col-6 col-lg-3"><div class="va-stock-metric"><small>Sold CTN</small><strong>{{ \App\Support\CartonNumber::format($summary->sold) }}</strong></div></div>
    <div class="col-6 col-lg-3"><div class="va-stock-metric"><small>Available CTN</small><strong>{{ \App\Support\CartonNumber::format((int) $summary->received - (int) $summary->sold) }}</strong></div></div>
</div>
<div class="table-responsive"><table class="table align-middle mb-0">
    <thead class="table-light"><tr><th>Product</th><th>Status</th><th class="text-end">Received CTN</th><th class="text-end">Sold CTN</th><th class="text-end">Available CTN</th></tr></thead>
    <tbody>
    @forelse($products as $product)
        @php $available = (int) ($product->stock_received ?? 0) - (int) ($product->stock_sold ?? 0); @endphp
        <tr><td><strong>{{ $product->name }}</strong></td><td><span class="badge {{ $product->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($product->status) }}</span></td>
            <td class="text-end">{{ \App\Support\CartonNumber::format($product->stock_received) }}</td>
            <td class="text-end">{{ \App\Support\CartonNumber::format($product->stock_sold) }}</td>
            <td class="text-end"><strong class="{{ $available > 0 ? 'text-success' : 'text-danger' }}">{{ \App\Support\CartonNumber::format($available) }}</strong></td></tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted py-5">No products found.</td></tr>
    @endforelse
    </tbody>
</table></div>
@include('shared.pagination', ['paginator' => $products, 'label' => 'Stock overview pagination'])
