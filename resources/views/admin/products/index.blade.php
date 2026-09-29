@extends('layouts.admin')

@section('title', 'Products Setting')
@section('page-title', 'Products Setting')
@section('page-action')
@include('shared.report-exports', ['reportModule' => 'products'])
    @if(\App\Support\Access::allowed('products', 'create'))<button type="button" class="btn btn-primary" id="newProduct"><i class="mdi mdi-plus me-1"></i> New Product</button>@endif
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="card-title mb-0">Products</h4>
        <button type="button" class="btn btn-outline-primary d-md-none" data-bs-toggle="offcanvas" data-bs-target="#productFilters" aria-controls="productFilters">
            <i class="mdi mdi-filter-variant me-1"></i> Filters
        </button>
    </div>
    <div class="card-body">
        <form id="desktopProductFilters" class="row g-2 align-items-end mb-4 d-none d-md-flex" action="{{ route('products.index') }}" method="GET">
            @include('admin.products.partials.filters')
        </form>
        <div id="productResults" class="va-results" aria-live="polite">
            @include('admin.products.partials.results')
        </div>
    </div>
</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalTitle" aria-hidden="true"
     data-store-url="{{ route('products.store') }}" data-reopen="{{ $errors->any() ? 'yes' : 'no' }}"
     data-edit-url="{{ $editingProduct ? route('products.update', $editingProduct) : '' }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="productForm" action="{{ $editingProduct ? route('products.update', $editingProduct) : route('products.store') }}" method="POST">
                @csrf
                <input type="hidden" name="_method" id="productMethod" value="{{ $editingProduct ? 'PUT' : 'POST' }}" disabled>
                <input type="hidden" name="form_context" id="productFormContext" value="{{ $editingProduct ? 'edit' : 'create' }}">
                <input type="hidden" name="product_id" id="productId" value="{{ $editingProduct?->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">{{ $editingProduct ? 'Edit Product' : 'New Product' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($errors->any())
                        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label" for="productName">Product name <span class="text-danger">*</span></label>
                        <input class="form-control @error('name') is-invalid @enderror" id="productName" name="name"
                               value="{{ old('name', $editingProduct?->name) }}" maxlength="150" required>
                    </div>
                    <div>
                        <label class="form-label" for="productStatus">Status <span class="text-danger">*</span></label>
                        <select class="form-select @error('status') is-invalid @enderror" id="productStatus" name="status" required>
                            <option value="active" @selected(old('status', $editingProduct?->status ?? 'active') === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $editingProduct?->status) === 'inactive')>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveProduct">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProductTitle">Delete Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">Delete <strong id="deleteProductName"></strong>? This product will be removed from the active list.</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteProductForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" id="confirmDeleteProduct">Delete Product</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="productFilters" aria-labelledby="productFiltersLabel">
    <div class="offcanvas-header">
        <h5 id="productFiltersLabel" class="offcanvas-title">Filter Products</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="mobileProductFilters" action="{{ route('products.index') }}" method="GET" class="row g-3">
            @include('admin.products.partials.filters')
        </form>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/products-setting.js') }}" defer></script>
@endpush
