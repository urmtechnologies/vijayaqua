<div class="col-12 col-md-3"><label class="form-label">Party, mobile or invoice</label><input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search payments..." maxlength="100"></div>
<div class="col-6 col-md-2"><label class="form-label">From date</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
<div class="col-6 col-md-2"><label class="form-label">To date</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
<div class="col-12 col-md-2"><label class="form-label">Method</label><select name="method" class="form-select">
    <option value="">All methods</option>
    @foreach(['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank', 'other' => 'Other'] as $value => $label)
        <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
    @endforeach
</select></div>
<div class="col-12 col-md-2"><label class="form-label">Approval</label><select name="approval" class="form-select"><option value="">All</option><option value="approved" @selected(request('approval') === 'approved')>Approved</option><option value="pending" @selected(request('approval') === 'pending')>Pending</option></select></div>
<div class="col-6 col-md-auto"><button type="submit" class="btn btn-primary w-100">Apply</button></div>
<div class="col-6 col-md-auto"><a href="{{ route('payments.index') }}" class="btn btn-outline-secondary w-100 va-clear-filters">Clear</a></div>
