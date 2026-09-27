<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead class="table-light"><tr>
            <th>Product name</th><th>Status</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse($products as $product)
            <tr>
                <td><strong>{{ $product->name }}</strong></td>
                <td><span class="badge {{ $product->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($product->status) }}</span></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-edit-product
                            data-product-id="{{ $product->id }}" data-product-name="{{ $product->name }}"
                            data-product-status="{{ $product->status }}" data-update-url="{{ route('products.update', $product) }}"
                            aria-label="Edit {{ $product->name }}">Edit</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-delete-product
                            data-product-name="{{ $product->name }}" data-delete-url="{{ route('products.destroy', $product) }}"
                            aria-label="Delete {{ $product->name }}">Delete</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="text-center text-muted py-5">No products found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('shared.pagination', ['paginator' => $products, 'label' => 'Products pagination'])
