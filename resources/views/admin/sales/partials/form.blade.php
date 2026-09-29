@php
    $submitted = old('items');
    $rows = is_array($submitted)
        ? array_filter($submitted, 'is_array')
        : $sale->items
            ->map(
                fn($item) => [
                    'product_id' => $item->product_id,
                    'cartons' => $item->cartons,
                    'rate_rupees' => $item->rate_rupees,
                    'discount_rupees' => $item->discount_rupees,
                    'reference_user_id' => $item->reference_user_id,
                ],
            )
            ->all();
    $rows = $rows ?: [
        ['product_id' => '', 'cartons' => '', 'rate_rupees' => '', 'discount_rupees' => '0', 'reference_user_id' => ''],
    ];
    $options = $products
        ->map(
            fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'available' => max(0, (int) ($p->stock_received ?? 0) - (int) ($p->stock_sold ?? 0)),
            ],
        )
        ->values();
@endphp
<form id="saleForm" method="POST" action="{{ route('sales.update', $sale) }}"
    data-old-discount="{{ $sale->discount_rupees }}" data-old-charge="{{ $sale->vehicle_charge_rupees }}">
    @csrf @method('PUT')
    <div class="card mb-3">
        <div class="card-header">
            <h4 class="card-title mb-0">New Sale · {{ $sale->invoice_no }}</h4><small class="text-muted">Add products,
                quantity, rate, discount and reference</small>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="editSaleDate">Sale Date
                        *</label><input id="editSaleDate" type="date" name="sale_date"
                        class="form-control @error('sale_date') is-invalid @enderror"
                        value="{{ old('sale_date', $sale->sale_date->format('Y-m-d')) }}" required>
                    @error('sale_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            @if ($errors->has('items'))
                <div class="alert alert-danger">{{ $errors->first('items') }}</div>
            @endif
            @if ($products->isEmpty())
                <div class="alert alert-info">No active products available. Add a product and stock first.</div>
            @endif
            <div id="saleRows" class="d-grid gap-3">
                @foreach ($rows as $index => $row)
                    @php $selected = $products->firstWhere('id', (int) ($row['product_id'] ?? 0)); @endphp
                    <div class="va-sale-row" data-sale-row>
                        <div class="va-sale-product"><label class="form-label"
                                for="saleProduct{{ $index }}">Product *</label>
                            <div class="va-sale-picker">
                                <input type="search" id="saleProduct{{ $index }}"
                                    class="form-control va-sale-search" placeholder="Search product..."
                                    autocomplete="off" value="{{ $selected?->name }}" required>
                                <input type="hidden" class="va-sale-product-id"
                                    name="items[{{ $index }}][product_id]"
                                    value="{{ $row['product_id'] ?? '' }}">
                                <div class="va-sale-options" hidden></div>
                            </div>
                            @error('items.' . $index . '.product_id')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="va-sale-qty-field"><label class="form-label"
                                for="saleCartons{{ $index }}">Qty (CTN) *</label><input type="number"
                                id="saleCartons{{ $index }}" name="items[{{ $index }}][cartons]"
                                class="form-control va-sale-qty" min="1" max="1000000000" step="1"
                                value="{{ $row['cartons'] ?? '' }}" required>
                            @error('items.' . $index . '.cartons')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="va-sale-rate-field"><label class="form-label"
                                for="saleRate{{ $index }}">Rate (₹) *</label><input type="number"
                                id="saleRate{{ $index }}" name="items[{{ $index }}][rate_rupees]"
                                class="form-control va-sale-rate" min="0" step="0.01"
                                value="{{ $row['rate_rupees'] ?? '' }}" required>
                            @error('items.' . $index . '.rate_rupees')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="va-sale-discount-field"><label class="form-label"
                                for="saleDiscount{{ $index }}">Discount (₹)</label><input type="number"
                                id="saleDiscount{{ $index }}"
                                name="items[{{ $index }}][discount_rupees]"
                                class="form-control va-sale-discount" min="0" step="0.01"
                                value="{{ $row['discount_rupees'] ?? '0' }}">
                            @error('items.' . $index . '.discount_rupees')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="va-sale-reference-field"><label class="form-label"
                                for="saleReference{{ $index }}">Reference User</label><select
                                id="saleReference{{ $index }}"
                                name="items[{{ $index }}][reference_user_id]"
                                class="form-select va-sale-reference">
                                <option value="">No reference</option>
                                @foreach ($staff as $person)
                                    <option value="{{ $person->id }}" @selected((string) ($row['reference_user_id'] ?? '') === (string) $person->id)>
                                        {{ $person->name }} · {{ $person->mobile }}</option>
                                @endforeach
                            </select>
                            @error('items.' . $index . '.reference_user_id')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="va-sale-line"><small>Amount</small><strong class="va-sale-line-total">₹0</strong>
                        </div>
                        <button type="button" class="btn btn-outline-danger va-sale-remove"
                            aria-label="Remove product row"><i class="ri-close-line"></i></button>
                    </div>
                @endforeach
            </div>
            <div class="text-center mt-3"><button type="button" class="btn btn-primary va-add-product-round"
                    id="addSaleRow" aria-label="Add more products" title="Add more products"
                    @disabled($products->isEmpty())><i class="ri-add-line" aria-hidden="true"></i></button></div>
            <div class="va-sale-totals mt-3">
                <div><span>Sale total</span><strong id="saleTotal">₹0</strong></div>
            </div>
            @if (
                \App\Support\SaleMoney::paise($sale->discount_rupees) ||
                    \App\Support\SaleMoney::paise($sale->vehicle_charge_rupees))
                <small class="d-block text-end text-muted mt-2">Previous invoice adjustment: −
                    ₹{{ \App\Support\SaleMoney::format($sale->discount_rupees) }} discount +
                    ₹{{ \App\Support\SaleMoney::format($sale->vehicle_charge_rupees) }} charge (preserved)</small>
            @endif
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2"><small
                class="text-muted">Added by {{ $sale->creator?->name ?? 'System' }}</small><button id="saveSale"
                class="btn btn-primary" type="submit" @disabled($products->isEmpty())><i class="ri-save-line me-1"></i>
                {{ $sale->is_draft ? 'Save Sale' : 'Update Sale' }}</button></div>
    </div>
</form>
<script id="saleProducts" type="application/json">@json($options)</script>
<template id="saleRowTemplate">
    <div class="va-sale-row" data-sale-row>
        <div class="va-sale-product"><label class="form-label">Product *</label>
            <div class="va-sale-picker"><input type="search" class="form-control va-sale-search"
                    placeholder="Search product..." autocomplete="off" required><input type="hidden"
                    class="va-sale-product-id">
                <div class="va-sale-options" hidden></div>
            </div>
        </div>
        <div class="va-sale-qty-field"><label class="form-label">Qty (CTN) *</label><input type="number"
                class="form-control va-sale-qty" min="1" max="1000000000" step="1" required></div>
        <div class="va-sale-rate-field"><label class="form-label">Rate (₹) *</label><input type="number"
                class="form-control va-sale-rate" min="0" step="0.01" required></div>
        <div class="va-sale-discount-field"><label class="form-label">Discount (₹)</label><input type="number"
                class="form-control va-sale-discount" min="0" step="0.01" value="0"></div>
        <div class="va-sale-reference-field"><label class="form-label">Reference User</label><select
                class="form-select va-sale-reference">
                <option value="">No reference</option>
                @foreach ($staff as $person)
                    <option value="{{ $person->id }}">{{ $person->name }} · {{ $person->mobile }}</option>
                @endforeach
            </select></div>
        <div class="va-sale-line"><small>Amount</small><strong class="va-sale-line-total">₹0</strong></div><button
            type="button" class="btn btn-outline-danger va-sale-remove" aria-label="Remove product row"><i
                class="ri-close-line"></i></button>
    </div>
</template>
