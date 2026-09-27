<div class="col-12 col-md-3">
    <label class="form-label">Party, mobile or invoice</label>
    <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search sales..." maxlength="100">
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
    <label class="form-label">Payment</label>
    <select name="status" class="form-select">
        <option value="">All invoices</option>
        <option value="paid" @selected(request('status') === 'paid')>Paid</option>
        <option value="due" @selected(request('status') === 'due')>Due</option>
    </select>
</div>
<div class="col-6 col-md-auto"><button type="submit" class="btn btn-primary w-100">Apply</button></div>
<div class="col-6 col-md-auto"><a href="{{ $filterUrl }}" class="btn btn-outline-secondary w-100 va-clear-filters">Clear</a></div>
