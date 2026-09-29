@php
    $submitted = old('items');
    $rows = is_array($submitted) ? array_filter($submitted, 'is_array') : $sale->items->map(fn ($item) => [
        'product_id' => $item->product_id, 'cartons' => $item->cartons, 'rate_rupees' => $item->rate_rupees,
    ])->all();
    $rows = $rows ?: [['product_id' => '', 'cartons' => '', 'rate_rupees' => '']];
    $options = $products->map(fn ($p) => [
        'id' => $p->id, 'name' => $p->name,
        'available' => max(0, (int) ($p->stock_received ?? 0) - (int) ($p->stock_sold ?? 0)),
    ])->values();
@endphp
<form id="saleForm" method="POST" action="{{ route('sales.update', $sale) }}">
    @csrf @method('PUT')
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h4 class="card-title mb-0">Products</h4><small class="text-muted">{{ $sale->invoice_no }} · {{ $sale->sale_date->format('d M Y') }}</small></div><button type="button" class="btn btn-outline-primary btn-sm" id="addSaleRow" @disabled($products->isEmpty())><i class="ri-add-line me-1"></i> Add More</button></div>
        <div class="card-body">
            @if($errors->has('items'))<div class="alert alert-danger">{{ $errors->first('items') }}</div>@endif
            @if($products->isEmpty())<div class="alert alert-info">No active products are available. Ask an administrator to add stock/products.</div>@endif
            <div id="saleRows" class="d-grid gap-3">
                @foreach($rows as $index => $row)
                @php $selected = $products->firstWhere('id', (int) ($row['product_id'] ?? 0)); @endphp
                <div class="va-sale-row" data-sale-row>
                    <div class="va-sale-row-number">{{ $loop->iteration }}</div>
                    <div class="va-sale-product"><label class="form-label" for="saleProduct{{ $index }}">Product <span class="text-danger">*</span></label><div class="va-sale-picker">
                        <input type="search" id="saleProduct{{ $index }}" class="form-control va-sale-search" placeholder="Search product..." autocomplete="off" value="{{ $selected?->name }}" required aria-label="Search product">
                        <input type="hidden" class="va-sale-product-id" name="items[{{ $index }}][product_id]" value="{{ $row['product_id'] ?? '' }}"><div class="va-sale-options" hidden></div>
                    </div>@error('items.'.$index.'.product_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
                    <div class="va-sale-qty-field"><label class="form-label" for="saleCartons{{ $index }}">Qty (CTN) <span class="text-danger">*</span></label><input type="number" id="saleCartons{{ $index }}" name="items[{{ $index }}][cartons]" class="form-control va-sale-qty" min="1" max="1000000000" step="1" value="{{ $row['cartons'] ?? '' }}" required>@error('items.'.$index.'.cartons')<small class="text-danger">{{ $message }}</small>@enderror</div>
                    <div class="va-sale-rate-field"><label class="form-label" for="saleRate{{ $index }}">Rate (₹/CTN) <span class="text-danger">*</span></label><input type="number" id="saleRate{{ $index }}" name="items[{{ $index }}][rate_rupees]" class="form-control va-sale-rate" min="0" max="9999999999999" step="0.01" inputmode="decimal" value="{{ $row['rate_rupees'] ?? '' }}" required>@error('items.'.$index.'.rate_rupees')<small class="text-danger">{{ $message }}</small>@enderror</div>
                    <div class="va-sale-line"><small>Amount</small><strong class="va-sale-line-total">₹0</strong></div>
                    <button type="button" class="btn btn-outline-danger va-sale-remove" aria-label="Remove product row"><i class="ri-close-line"></i></button>
                </div>
                @endforeach
            </div>
            <div class="va-sale-totals mt-3"><div><span>Sale total</span><strong id="saleTotal">₹0</strong></div></div>
        </div>
    </div>
    <div class="card mb-3"><div class="card-header"><h4 class="card-title mb-0">Reference</h4></div><div class="card-body row g-3">
        <div class="col-12 col-md-6"><label for="staffSearch" class="form-label">Search staff</label><input id="staffSearch" type="search" class="form-control" placeholder="Type a staff name or mobile..."></div>
        <div class="col-12 col-md-6"><label for="staffSelect" class="form-label">Reference User</label><select id="staffSelect" name="reference_user_id" class="form-select"><option value="">No reference</option>@foreach($staff as $person)<option value="{{ $person->id }}" data-search="{{ strtolower($person->name.' '.$person->mobile) }}" @selected((string) old('reference_user_id', $sale->reference_user_id) === (string) $person->id)>{{ $person->name }} · {{ $person->mobile }}</option>@endforeach</select>@error('reference_user_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
        <div class="col-12"><small class="text-muted">Added by {{ $sale->creator?->name ?? auth()->user()->name }} · Payments are recorded in the party account after approval.</small></div>
    </div><div class="card-footer d-flex justify-content-end gap-2 flex-wrap"><a href="{{ route('customers.show', $sale->customer) }}" class="btn btn-light">Cancel</a><button id="saveSale" class="btn btn-primary" type="submit" @disabled($products->isEmpty())><i class="ri-save-line me-1"></i> Save Sale</button></div></div>
</form>
<script id="saleProducts" type="application/json">@json($options)</script>
<template id="saleRowTemplate"><div class="va-sale-row" data-sale-row>
    <div class="va-sale-row-number"></div>
    <div class="va-sale-product"><label class="form-label">Product <span class="text-danger">*</span></label><div class="va-sale-picker"><input type="search" class="form-control va-sale-search" placeholder="Search product..." autocomplete="off" required><input type="hidden" class="va-sale-product-id"><div class="va-sale-options" hidden></div></div></div>
    <div class="va-sale-qty-field"><label class="form-label">Qty (CTN) <span class="text-danger">*</span></label><input type="number" class="form-control va-sale-qty" min="1" max="1000000000" step="1" required></div>
    <div class="va-sale-rate-field"><label class="form-label">Rate (₹/CTN) <span class="text-danger">*</span></label><input type="number" class="form-control va-sale-rate" min="0" max="9999999999999" step="0.01" inputmode="decimal" required></div>
    <div class="va-sale-line"><small>Amount</small><strong class="va-sale-line-total">₹0</strong></div><button type="button" class="btn btn-outline-danger va-sale-remove" aria-label="Remove product row"><i class="ri-close-line"></i></button>
</div></template>
