<div class="col-12 col-md-4">
    <label class="form-label">Product name</label>
    <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search products..." maxlength="100">
</div>
<div class="col-12 col-md-2">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
        <option value="">All statuses</option>
        <option value="active" @selected(request('status') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
    </select>
</div>
<div class="col-12 col-md-3">
    <label class="form-label">Sort by</label>
    <select name="sort" class="form-select">
        <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest first</option>
        <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
        <option value="name" @selected(request('sort') === 'name')>Name A–Z</option>
    </select>
</div>
<div class="col-12 col-md-auto">
    <button type="submit" class="btn btn-primary w-100">Apply</button>
</div>
<div class="col-12 col-md-auto">
    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary w-100 va-clear-filters">Clear</a>
</div>
