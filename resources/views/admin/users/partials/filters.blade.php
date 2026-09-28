<div class="col-12 col-md-4">
    <label class="form-label">Name or mobile</label>
    <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search staff..." maxlength="100">
</div>
<div class="col-12 col-md-2">
    <label class="form-label">Role</label>
    <select name="role" class="form-select">
        <option value="">All roles</option>
        @foreach($roles as $roleOption)
            <option value="{{ $roleOption }}" @selected(request('role') === $roleOption)>{{ ucfirst($roleOption) }}</option>
        @endforeach
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
<div class="col-12 col-md-2"><label class="form-label">Approval</label><select name="approval" class="form-select"><option value="">All</option><option value="approved" @selected(request('approval') === 'approved')>Approved</option><option value="pending" @selected(request('approval') === 'pending')>Pending</option></select></div>
<div class="col-12 col-md-auto">
    <button type="submit" class="btn btn-primary w-100">Apply</button>
</div>
<div class="col-12 col-md-auto">
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary w-100 va-clear-filters">Clear</a>
</div>
