@php
    $submittedRows = old('items');
    $rows = is_array($submittedRows)
        ? array_filter($submittedRows, 'is_array')
        : ($entry?->items->map(fn ($item) => ['product_id' => $item->product_id, 'cartons' => $item->cartons])->all() ?? []);
    $rows = $rows ?: [['product_id' => '', 'cartons' => '']];
    $productOptions = $products->map(fn ($product) => ['id' => $product->id, 'name' => $product->name, 'available' => $product->status === 'active' && ! $product->trashed()])->values();
@endphp
<form action="{{ $entry ? route('stock-entries.update', $entry) : route('stock-entries.store') }}" method="POST" id="stockEntryForm">
    @csrf
    @if($entry) @method('PUT') @endif

    <div class="card mb-3">
        <div class="card-header"><h4 class="card-title mb-0">Entry Date</h4></div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label for="entryDate" class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" id="entryDate" name="entry_date" class="form-control @error('entry_date') is-invalid @enderror"
                       value="{{ old('entry_date', $entry?->entry_date?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                @error('entry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="stockType" class="form-label">Type <span class="text-danger">*</span></label>
                <select id="stockType" name="type" class="form-select @error('type') is-invalid @enderror" required>
                    <option value="">Select type</option>
                    <option value="manufacture" @selected(old('type', $entry?->type) === 'manufacture')>Manufacture</option>
                    <option value="purchase" @selected(old('type', $entry?->type) === 'purchase')>Purchase</option>
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div><h4 class="card-title mb-0">Products</h4><small class="text-muted">Add each product once, with its carton quantity.</small></div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addStockRow" @if($products->isEmpty()) disabled @endif>
                <i class="mdi mdi-plus me-1"></i> Add Product
            </button>
        </div>
        <div class="card-body">
            @if($errors->has('items'))<div class="alert alert-danger" role="alert">{{ $errors->first('items') }}</div>@endif
            @if($products->isEmpty())
                <div class="alert alert-info">Create an active product in <a href="{{ route('products.index') }}">Products Setting</a> first.</div>
            @endif
            <div id="stockRows" class="d-grid gap-3">
                @foreach($rows as $index => $row)
                    @php $selectedProduct = $products->firstWhere('id', (int) ($row['product_id'] ?? 0)); @endphp
                    <div class="va-stock-row" data-stock-row>
                        <div class="va-stock-row-number" aria-hidden="true">{{ $loop->iteration }}</div>
                        <div class="va-stock-product">
                            <label class="form-label" for="stockProduct{{ $index }}">Select Product <span class="text-danger">*</span></label>
                            <div class="va-stock-picker">
                                <input type="search" id="stockProduct{{ $index }}" class="form-control va-stock-search @error('items.'.$index.'.product_id') is-invalid @enderror"
                                       role="combobox" aria-autocomplete="list" aria-expanded="false" autocomplete="off"
                                       placeholder="Search product..." value="{{ $selectedProduct?->name }}" required>
                                <input type="hidden" name="items[{{ $index }}][product_id]" class="va-stock-product-id" value="{{ $row['product_id'] ?? '' }}">
                                <div class="va-stock-options" role="listbox" hidden></div>
                            </div>
                            @error('items.'.$index.'.product_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="va-stock-cartons">
                            <label class="form-label" for="stockCartons{{ $index }}">Quantity (CTN) <span class="text-danger">*</span></label>
                            <input type="number" id="stockCartons{{ $index }}" name="items[{{ $index }}][cartons]"
                                   class="form-control va-stock-qty @error('items.'.$index.'.cartons') is-invalid @enderror"
                                   min="1" max="1000000000" step="1" inputmode="numeric" value="{{ $row['cartons'] ?? '' }}" placeholder="CTN" required>
                            @error('items.'.$index.'.cartons')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="button" class="btn btn-outline-danger va-stock-remove" aria-label="Remove product row" title="Remove row"><i class="mdi mdi-close"></i></button>
                    </div>
                @endforeach
            </div>
            <p class="text-muted small mt-3 mb-0">Add as many different products as needed.</p>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="va-stock-form-total" aria-live="polite"><span>Total</span><strong id="stockTotal">0 CTN</strong></div>
            <div class="d-flex gap-2">
                <a href="{{ route('stock-entries.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" id="saveStockEntry" class="btn btn-primary" @if($products->isEmpty()) disabled @endif>
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="stockSavingSpinner" aria-hidden="true"></span>
                    <span id="stockSaveLabel">{{ $entry ? 'Save Changes' : 'Save Stock Entry' }}</span>
                </button>
            </div>
        </div>
    </div>
</form>

<script type="application/json" id="stockProducts">@json($productOptions)</script>

<template id="stockRowTemplate">
    <div class="va-stock-row" data-stock-row>
        <div class="va-stock-row-number" aria-hidden="true"></div>
        <div class="va-stock-product">
            <label class="form-label">Select Product <span class="text-danger">*</span></label>
            <div class="va-stock-picker">
                <input type="search" class="form-control va-stock-search" role="combobox" aria-autocomplete="list"
                       aria-expanded="false" autocomplete="off" placeholder="Search product..." required>
                <input type="hidden" class="va-stock-product-id">
                <div class="va-stock-options" role="listbox" hidden></div>
            </div>
        </div>
        <div class="va-stock-cartons">
            <label class="form-label">Quantity (CTN) <span class="text-danger">*</span></label>
            <input type="number" class="form-control va-stock-qty" min="1" max="1000000000" step="1" inputmode="numeric" placeholder="CTN" required>
        </div>
        <button type="button" class="btn btn-outline-danger va-stock-remove" aria-label="Remove product row" title="Remove row"><i class="mdi mdi-close"></i></button>
    </div>
</template>
