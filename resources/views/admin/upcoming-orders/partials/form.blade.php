@php
    $submitted = old('items');
    $rows = is_array($submitted) ? array_filter($submitted, 'is_array')
        : ($order?->items->map(fn ($item) => ['product_id' => $item->product_id, 'cartons' => $item->cartons])->all() ?? []);
    $rows = $rows ?: [['product_id' => '', 'cartons' => '']];
    $productOptions = $products->map(fn ($product) => [
        'id' => $product->id, 'name' => $product->name,
        'selectable' => $product->status === 'active' && ! $product->trashed(),
    ])->values();
@endphp
<form method="POST" action="{{ $order ? route('upcoming-orders.update', $order) : route('upcoming-orders.store') }}"
      id="upcomingOrderForm" data-customer-lookup="{{ route('customers.lookup') }}">
    @csrf
    @if($order) @method('PUT') @endif
    <div class="card mb-3"><div class="card-header"><h4 class="card-title mb-0">Customer & Date</h4></div>
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label" for="upcomingMobile">Mobile Number <span class="text-danger">*</span></label>
                <input type="tel" id="upcomingMobile" name="mobile" class="form-control @error('mobile') is-invalid @enderror" value="{{ old('mobile', $customer?->mobile) }}" pattern="[0-9]{10}" maxlength="10" inputmode="numeric" autocomplete="tel" required>
                @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4"><label class="form-label" for="upcomingCustomer">Customer Name <span class="text-danger">*</span></label>
                <input id="upcomingCustomer" name="customer_name" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name', $customer?->name) }}" maxlength="150" required>
                @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4"><label class="form-label" for="upcomingDate">Scheduled Date <span class="text-danger">*</span></label>
                <input type="date" id="upcomingDate" name="scheduled_date" class="form-control @error('scheduled_date') is-invalid @enderror" value="{{ old('scheduled_date', $order?->scheduled_date?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                @error('scheduled_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12"><div id="upcomingCustomerInfo" class="va-party-lookup" aria-live="polite"></div></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2"><div><h4 class="card-title mb-0">Products</h4><small class="text-muted">Add each product once with its quantity in CTN.</small></div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addUpcomingRow" @if($products->isEmpty()) disabled @endif><i class="mdi mdi-plus me-1"></i> Add Product</button>
        </div>
        <div class="card-body">
            @if($errors->has('items'))<div class="alert alert-danger" role="alert">{{ $errors->first('items') }}</div>@endif
            @if($products->isEmpty())<div class="alert alert-info">Create an active product in <a href="{{ route('products.index') }}">Products Setting</a> first.</div>@endif
            <div id="upcomingRows" class="d-grid gap-3">
                @foreach($rows as $index => $row)
                    @php $selected = $products->firstWhere('id', (int) ($row['product_id'] ?? 0)); @endphp
                    <div class="va-order-row" data-order-row>
                        <div class="va-order-row-number" aria-hidden="true">{{ $loop->iteration }}</div>
                        <div class="va-order-product"><label class="form-label" for="upcomingProduct{{ $index }}">Product <span class="text-danger">*</span></label>
                            <div class="va-order-picker"><input type="search" id="upcomingProduct{{ $index }}" class="form-control va-order-search @error('items.'.$index.'.product_id') is-invalid @enderror" role="combobox" aria-autocomplete="list" aria-expanded="false" autocomplete="off" placeholder="Search product..." value="{{ $selected?->name }}" required>
                                <input type="hidden" class="va-order-product-id" name="items[{{ $index }}][product_id]" value="{{ $row['product_id'] ?? '' }}"><div class="va-order-options" role="listbox" hidden></div></div>
                            @error('items.'.$index.'.product_id')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="va-order-qty-field"><label class="form-label" for="upcomingCartons{{ $index }}">Qty (CTN) <span class="text-danger">*</span></label>
                            <input type="number" id="upcomingCartons{{ $index }}" name="items[{{ $index }}][cartons]" class="form-control va-order-qty @error('items.'.$index.'.cartons') is-invalid @enderror" min="1" max="1000000000" step="1" inputmode="numeric" value="{{ $row['cartons'] ?? '' }}" required>
                            @error('items.'.$index.'.cartons')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="button" class="btn btn-outline-danger va-order-remove" aria-label="Remove product row"><i class="mdi mdi-close"></i></button>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end"><div class="va-stock-form-total" aria-live="polite"><span>Total</span><strong id="upcomingTotal">0 CTN</strong></div></div>
    </div>

    <div class="card"><div class="card-header"><h4 class="card-title mb-0">Note</h4></div>
        <div class="card-body"><label for="upcomingNote" class="form-label">Notes (optional)</label><textarea id="upcomingNote" name="note" class="form-control @error('note') is-invalid @enderror" rows="4" maxlength="3000">{{ old('note', $order?->note) }}</textarea>
            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="text-muted small mb-0 mt-2">Upcoming orders are planning records. Stock changes only when you create a sale.</p>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2"><a href="{{ $order ? route('upcoming-orders.show', $order) : route('upcoming-orders.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" id="saveUpcoming" class="btn btn-primary" @if($products->isEmpty()) disabled @endif><span id="upcomingSpinner" class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span><span id="upcomingButtonText">{{ $order ? 'Save Changes' : 'Save Upcoming Order' }}</span></button>
        </div>
    </div>
</form>
<script type="application/json" id="upcomingProducts">@json($productOptions)</script>
<template id="upcomingRowTemplate">
    <div class="va-order-row" data-order-row><div class="va-order-row-number" aria-hidden="true"></div>
        <div class="va-order-product"><label class="form-label">Product <span class="text-danger">*</span></label><div class="va-order-picker"><input type="search" class="form-control va-order-search" role="combobox" aria-autocomplete="list" aria-expanded="false" autocomplete="off" placeholder="Search product..." required><input type="hidden" class="va-order-product-id"><div class="va-order-options" role="listbox" hidden></div></div></div>
        <div class="va-order-qty-field"><label class="form-label">Qty (CTN) <span class="text-danger">*</span></label><input type="number" class="form-control va-order-qty" min="1" max="1000000000" step="1" inputmode="numeric" required></div>
        <button type="button" class="btn btn-outline-danger va-order-remove" aria-label="Remove product row"><i class="mdi mdi-close"></i></button>
    </div>
</template>
