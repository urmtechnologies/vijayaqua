@php
    $submitted = old('items');
    $rows = is_array($submitted) ? array_filter($submitted, 'is_array')
        : ($sale?->items->map(fn ($item) => ['product_id' => $item->product_id, 'cartons' => $item->cartons, 'rate_rupees' => $item->rate_rupees])->all() ?? []);
    $rows = $rows ?: [['product_id' => '', 'cartons' => '', 'rate_rupees' => '']];
    $productOptions = $products->map(fn ($product) => [
        'id' => $product->id,
        'name' => $product->name,
        'available' => max(0, (int) ($product->stock_received ?? 0) - (int) ($product->stock_sold ?? 0)),
    ])->values();
@endphp
<form id="saleForm" method="POST" action="{{ $sale ? route('sales.update', $sale) : route('sales.store') }}"
      data-customer-lookup="{{ route('customers.lookup') }}">
    @csrf
    @if($sale) @method('PUT') @endif
    <div class="card mb-3">
        <div class="card-header"><h4 class="card-title mb-0">Party & Invoice</h4></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label for="partyMobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                <input type="tel" id="partyMobile" name="mobile" class="form-control @error('mobile') is-invalid @enderror"
                       value="{{ old('mobile', $customer?->mobile) }}" pattern="[0-9]{10}" maxlength="10" inputmode="numeric" autocomplete="tel" required>
                @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="partyName" class="form-label">Party Name <span class="text-danger">*</span></label>
                <input id="partyName" name="party_name" class="form-control @error('party_name') is-invalid @enderror"
                       value="{{ old('party_name', $customer?->name) }}" maxlength="150" required>
                @error('party_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="saleDate" class="form-label">Sale Date <span class="text-danger">*</span></label>
                <input type="date" id="saleDate" name="sale_date" class="form-control @error('sale_date') is-invalid @enderror"
                       value="{{ old('sale_date', $sale?->sale_date?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                @error('sale_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div id="partyLookup" class="va-party-lookup" aria-live="polite"
                     data-initial-url="{{ $customer && \App\Support\Access::allowed('sales') ? route('customers.show', $customer) : '' }}"
                     data-initial-name="{{ $customer?->name }}"></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><h4 class="card-title mb-0">Products</h4><small class="text-muted">Each product appears once on this invoice.</small></div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addSaleRow" @if($products->isEmpty()) disabled @endif>Add Product</button>
        </div>
        <div class="card-body">
            @if($errors->has('items'))<div class="alert alert-danger">{{ $errors->first('items') }}</div>@endif
            @if($products->isEmpty())
                <div class="alert alert-info">
                    No active product is available.
                    @if(\App\Support\Access::allowed('products'))
                        <a href="{{ route('products.index') }}">Open Products Setting</a>.
                    @else
                        Ask an administrator to add one.
                    @endif
                </div>
            @endif
            <div id="saleRows" class="d-grid gap-3">
                @foreach($rows as $index => $row)
                    @php $selectedProduct = $products->firstWhere('id', (int) ($row['product_id'] ?? 0)); @endphp
                    <div class="va-sale-row" data-sale-row>
                        <div class="va-sale-row-number">{{ $loop->iteration }}</div>
                        <div class="va-sale-product">
                            <label class="form-label" for="saleProduct{{ $index }}">Product <span class="text-danger">*</span></label>
                            <div class="va-sale-picker">
                                <input type="search" id="saleProduct{{ $index }}" class="form-control va-sale-search @error('items.'.$index.'.product_id') is-invalid @enderror"
                                       role="combobox" aria-autocomplete="list" aria-expanded="false" autocomplete="off"
                                       placeholder="Search product..." value="{{ $selectedProduct?->name }}" required>
                                <input type="hidden" class="va-sale-product-id" name="items[{{ $index }}][product_id]" value="{{ $row['product_id'] ?? '' }}">
                                <div class="va-sale-options" role="listbox" hidden></div>
                            </div>
                            @error('items.'.$index.'.product_id')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="va-sale-qty-field"><label class="form-label" for="saleCartons{{ $index }}">Qty (CTN) <span class="text-danger">*</span></label>
                            <input type="number" id="saleCartons{{ $index }}" class="form-control va-sale-qty @error('items.'.$index.'.cartons') is-invalid @enderror"
                                   name="items[{{ $index }}][cartons]" min="1" max="1000000000" step="1" inputmode="numeric" value="{{ $row['cartons'] ?? '' }}" required>
                            @error('items.'.$index.'.cartons')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="va-sale-rate-field"><label class="form-label" for="saleRate{{ $index }}">Rate (₹/CTN) <span class="text-danger">*</span></label>
                            <input type="number" id="saleRate{{ $index }}" class="form-control va-sale-rate @error('items.'.$index.'.rate_rupees') is-invalid @enderror"
                                   name="items[{{ $index }}][rate_rupees]" min="0" max="9999999999" step="1" inputmode="numeric" value="{{ $row['rate_rupees'] ?? '' }}" required>
                            @error('items.'.$index.'.rate_rupees')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="va-sale-line"><small>Amount</small><strong class="va-sale-line-total">₹0</strong></div>
                        <button type="button" class="btn btn-outline-danger va-sale-remove" aria-label="Remove product row"><i class="mdi mdi-close"></i></button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h4 class="card-title mb-0">Amount & Payment</h4></div>
        <div class="card-body row g-3">
            <div class="col-sm-6 col-lg-3"><label for="saleDiscount" class="form-label">Discount (₹)</label>
                <input type="number" id="saleDiscount" name="discount_rupees" class="form-control @error('discount_rupees') is-invalid @enderror" min="0" step="1" value="{{ old('discount_rupees', $sale?->discount_rupees ?? 0) }}" required>
                @error('discount_rupees')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3"><label for="saleVehicle" class="form-label">Vehicle Charge (₹)</label>
                <input type="number" id="saleVehicle" name="vehicle_charge_rupees" class="form-control @error('vehicle_charge_rupees') is-invalid @enderror" min="0" step="1" value="{{ old('vehicle_charge_rupees', $sale?->vehicle_charge_rupees ?? 0) }}" required>
                @error('vehicle_charge_rupees')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3"><label for="salePaid" class="form-label">{{ $sale ? 'Already Paid (₹)' : 'Paid Now (₹)' }}</label>
                <input type="number" id="salePaid" @unless($sale) name="initial_paid_rupees" @endunless class="form-control @error('initial_paid_rupees') is-invalid @enderror" min="0" step="1" value="{{ $sale ? $paid : old('initial_paid_rupees', 0) }}" @if($sale) readonly @endif required>
                @error('initial_paid_rupees')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3"><label for="saleDueDate" class="form-label">Due Date (if due)</label>
                <input type="date" id="saleDueDate" name="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date', $sale?->due_date?->format('Y-m-d')) }}">
                @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @unless($sale)
            <div class="col-md-6"><label for="salePaymentMethod" class="form-label">Paid Via</label>
                <select id="salePaymentMethod" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                    <option value="">Select method if paid</option>
                    @foreach(['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label for="salePaymentReference" class="form-label">Payment Reference (optional)</label>
                <input id="salePaymentReference" name="payment_reference" class="form-control" maxlength="150" value="{{ old('payment_reference') }}">
            </div>
            @else
                <div class="col-12"><p class="text-muted small mb-0">Recorded payments stay unchanged. Add later payments from the invoice page.</p></div>
            @endunless
            <div class="col-md-12"><label for="saleReference" class="form-label">Sale Reference (optional)</label>
                <input id="saleReference" name="reference" class="form-control" maxlength="150" value="{{ old('reference', $sale?->reference) }}">
            </div>
            <div class="col-12"><div class="va-sale-totals" aria-live="polite">
                <div><span>Products</span><strong id="saleSubtotal">₹0</strong></div>
                <div><span>Invoice total</span><strong id="saleTotal">₹0</strong></div>
                <div><span>Due after payment</span><strong id="saleDue">₹0</strong></div>
            </div></div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ $sale && \App\Support\Access::allowed('sales', 'invoice') ? route('sales.show', $sale) : route('sales.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" id="saveSale" class="btn btn-primary" @if($products->isEmpty()) disabled @endif>
                <span id="saleSpinner" class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span>
                <span id="saleButtonText">{{ $sale ? 'Save Changes' : 'Create Invoice' }}</span>
            </button>
        </div>
    </div>
</form>

<script type="application/json" id="saleProducts">@json($productOptions)</script>
<template id="saleRowTemplate">
    <div class="va-sale-row" data-sale-row>
        <div class="va-sale-row-number"></div>
        <div class="va-sale-product"><label class="form-label">Product <span class="text-danger">*</span></label><div class="va-sale-picker">
            <input type="search" class="form-control va-sale-search" role="combobox" aria-autocomplete="list" aria-expanded="false" autocomplete="off" placeholder="Search product..." required>
            <input type="hidden" class="va-sale-product-id"><div class="va-sale-options" role="listbox" hidden></div>
        </div></div>
        <div class="va-sale-qty-field"><label class="form-label">Qty (CTN) <span class="text-danger">*</span></label><input type="number" class="form-control va-sale-qty" min="1" max="1000000000" step="1" inputmode="numeric" required></div>
        <div class="va-sale-rate-field"><label class="form-label">Rate (₹/CTN) <span class="text-danger">*</span></label><input type="number" class="form-control va-sale-rate" min="0" max="9999999999" step="1" inputmode="numeric" required></div>
        <div class="va-sale-line"><small>Amount</small><strong class="va-sale-line-total">₹0</strong></div>
        <button type="button" class="btn btn-outline-danger va-sale-remove" aria-label="Remove product row"><i class="mdi mdi-close"></i></button>
    </div>
</template>
