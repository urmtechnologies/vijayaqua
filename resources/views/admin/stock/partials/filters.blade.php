<div class="col-12 col-md-2">
    <label class="form-label">Product</label>
    <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search product..." maxlength="100">
</div>
<div class="col-12 col-md-2">
    <label class="form-label">Type</label>
    <select name="type" class="form-select">
        <option value="">All types</option>
        <option value="manufacture" @selected(request('type') === 'manufacture')>Manufacture</option>
        <option value="purchase" @selected(request('type') === 'purchase')>Purchase</option>
    </select>
</div>
<div class="col-6 col-md-2">
    <label class="form-label">From date</label>
    <input type="date" name="from" value="{{ request('from') }}" class="form-control">
</div>
<div class="col-6 col-md-2">
    <label class="form-label">To date</label>
    <input type="date" name="to" value="{{ request('to') }}" class="form-control">
</div>
<div class="col-12 col-md-2">
    <label class="form-label">Sort by</label>
    <select name="sort" class="form-select">
        <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest first</option>
        <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
    </select>
</div>
<div class="col-6 col-md-auto">
    <button type="submit" class="btn btn-primary w-100">Apply</button>
</div>
<div class="col-6 col-md-auto">
    <a href="{{ route('stock-entries.index') }}" class="btn btn-outline-secondary w-100 va-clear-filters">Clear</a>
</div>
