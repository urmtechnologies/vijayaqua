<div class="col-12 col-md-4"><label class="form-label">Customer / Product</label><input type="search" name="search"
        class="form-control" value="{{ request('search') }}" placeholder="Name, mobile or product..." maxlength="100"></div>
<div class="col-6 col-md-2"><label class="form-label">From date</label><input type="date" name="from"
        class="form-control" value="{{ request('from') }}"></div>
<div class="col-6 col-md-2"><label class="form-label">To date</label><input type="date" name="to"
        class="form-control" value="{{ request('to') }}"></div>
<div class="col-12 col-md-auto"><label class="form-label">Sort</label><select name="sort" class="form-select">
        <option value="soonest" @selected(request('sort', 'soonest') === 'soonest')>Soonest</option>
        <option value="latest" @selected(request('sort') === 'latest')>Latest</option>
    </select></div>
<div class="col-12 col-md-2"><label class="form-label">Approval</label><select name="approval" class="form-select">
        <option value="">All</option>
        <option value="approved" @selected(request('approval') === 'approved')>Approved</option>
        <option value="pending" @selected(request('approval') === 'pending')>Pending</option>
    </select></div>
<div class="col-6 col-md-auto"><button type="submit" class="btn btn-primary w-100">Apply</button></div>
<div class="col-6 col-md-auto"><a href="{{ route('upcoming-orders.index') }}"
        class="btn btn-outline-secondary w-100 va-clear-filters">Clear</a></div>
