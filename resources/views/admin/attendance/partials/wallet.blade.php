<section class="card va-staff-panel" id="salaryWallet" aria-labelledby="salaryTitle"><div class="card-body">
    <div class="va-staff-section-heading"><div><span class="va-staff-eyebrow">Salary account</span><h5 id="salaryTitle">Salary & wallet</h5><p>Credit = paid to staff · Debit = money returned by staff.</p></div><span class="va-staff-section-icon va-staff-icon-green"><i class="ri-wallet-3-line" aria-hidden="true"></i></span></div>
    @if(auth()->user()->role === 'admin')
        <form method="GET" action="{{ route('attendance.index') }}" class="va-staff-select-form row g-2 align-items-end">
            <input type="hidden" name="month" value="{{ $month }}">
            <div class="col-12 col-sm-8 col-md-5"><label class="form-label" for="walletEmployee">Staff member</label><select id="walletEmployee" name="employee_id" class="form-select" required><option value="">Select staff</option>@foreach($staffOptions as $person)<option value="{{ $person->id }}" @selected($selectedEmployee?->id === $person->id)>{{ $person->name }} · {{ $person->mobile }}</option>@endforeach</select></div>
            <div class="col-12 col-sm-4 col-md-2"><button type="submit" class="btn btn-outline-primary w-100">View account</button></div>
        </form>
    @endif
    @if($selectedEmployee)
        @php $selection = $monthly[$selectedEmployee->id]; @endphp
        <div class="va-staff-wallet-name"><strong>{{ $selectedEmployee->name }}</strong><span>{{ $selectedEmployee->mobile }} · {{ $start->format('F Y') }}</span></div>
        <div class="va-staff-wallet-metrics">
            <div><small>Generated salary</small><strong>₹{{ \App\Support\SalaryMath::format($wallet['earned']) }}</strong></div>
            <div><small>Total paid</small><strong class="text-success">₹{{ \App\Support\SalaryMath::format($wallet['paid']) }}</strong></div>
            <div><small>Returned</small><strong>₹{{ \App\Support\SalaryMath::format($wallet['returned']) }}</strong></div>
            <div><small>Balance</small><strong class="{{ $wallet['balance'] > 0 ? 'text-danger' : 'text-success' }}">{{ $wallet['balance'] < 0 ? '−' : '' }}₹{{ \App\Support\SalaryMath::format(abs($wallet['balance'])) }}</strong></div>
        </div>
        <div class="va-staff-month-overview"><div><small>Monthly rate</small><strong>₹{{ \App\Support\SalaryMath::format($selection['rate']) }}</strong></div><div><small>{{ $selection['run'] ? 'Generated for '.$start->format('M Y') : 'Earned so far in '.$start->format('M Y') }}</small><strong>₹{{ \App\Support\SalaryMath::format($selection['earned']) }}</strong></div><div><small>Pending approval</small><strong>{{ $selection['pending'] }}</strong></div></div>
        @if(auth()->user()->role === 'admin')
            <div class="va-staff-wallet-actions">
                @if($month < now()->format('Y-m') && ! $selection['run'] && ! $selection['pending'])
                    <form method="POST" action="{{ route('salaries.store') }}">@csrf<input type="hidden" name="employee_id" value="{{ $selectedEmployee->id }}"><input type="hidden" name="month" value="{{ $month }}"><button type="submit" class="btn btn-primary">Generate {{ $start->format('M Y') }} salary</button></form>
                @elseif($selection['run'])
                    <span class="va-staff-generated">{{ $start->format('M Y') }} salary generated · Added by {{ $selection['run']->creator?->name ?? 'System' }}</span>
                @elseif($selection['pending'])
                    <small class="text-warning-emphasis">Approve pending attendance before generating salary.</small>
                @else
                    <small class="text-muted">Salary can be generated after the month ends.</small>
                @endif
            </div>
            <form method="POST" action="{{ route('salaries.advance') }}" class="va-staff-payment-form row g-2 align-items-end">@csrf
                <input type="hidden" name="employee_id" value="{{ $selectedEmployee->id }}"><input type="hidden" name="month" value="{{ $month }}">
                <div class="col-6 col-md-2"><label class="form-label" for="walletType">Entry *</label><select id="walletType" name="entry_type" class="form-select"><option value="credit" @selected(old('entry_type') !== 'debit')>Credit · paid</option><option value="debit" @selected(old('entry_type') === 'debit')>Debit · returned</option></select></div>
                <div class="col-6 col-md-2"><label class="form-label" for="walletDate">Date *</label><input id="walletDate" name="paid_on" type="date" max="{{ now()->toDateString() }}" value="{{ old('paid_on', now()->toDateString()) }}" class="form-control" required></div>
                <div class="col-6 col-md-2"><label class="form-label" for="walletAmount">Amount (₹) *</label><input id="walletAmount" name="amount_rupees" type="number" min="0.01" step="0.01" value="{{ old('amount_rupees') }}" class="form-control" required></div>
                <div class="col-6 col-md-2"><label class="form-label" for="walletMethod">Method *</label><select id="walletMethod" name="method" class="form-select"><option value="cash">Cash</option><option value="upi">UPI</option><option value="bank">Bank</option><option value="other">Other</option></select></div>
                <div class="col-12 col-md-2"><label class="form-label" for="walletNote">Note</label><input id="walletNote" name="note" class="form-control" maxlength="500" value="{{ old('note') }}" placeholder="Optional"></div>
                <div class="col-12 col-md-2"><button type="submit" class="btn btn-outline-primary w-100">Save entry</button></div>
            </form>
        @endif
        <div class="va-staff-history-heading"><h6>Payment history</h6><small>{{ $history->count() }} entries · Added by is recorded automatically</small></div>
        <div class="va-staff-history" aria-label="Payment history for {{ $selectedEmployee->name }}">
            @forelse($history as $entry)
                <div class="va-staff-transaction"><div class="va-staff-transaction-main"><span class="va-staff-transaction-icon {{ $entry['type'] === 'debit' ? 'is-return' : 'is-credit' }}"><i class="{{ $entry['type'] === 'debit' ? 'ri-arrow-up-line' : 'ri-arrow-down-line' }}" aria-hidden="true"></i></span><div><strong>{{ $entry['type'] === 'debit' ? 'Debit · returned' : 'Credit · paid' }}</strong><small>{{ $entry['date']->format('d M Y') }} · {{ $entry['month'] }} · {{ strtoupper($entry['method']) }}</small>@if($entry['note'])<small>{{ $entry['note'] }}</small>@endif<small>Added by {{ $entry['creator'] ?? 'System' }}</small></div></div><strong class="{{ $entry['type'] === 'debit' ? 'text-danger' : 'text-success' }}">{{ $entry['type'] === 'debit' ? '−' : '+' }}₹{{ \App\Support\SalaryMath::format($entry['amount']) }}</strong></div>
            @empty <p class="text-muted mb-0 py-3">No payments recorded for this staff member yet.</p> @endforelse
        </div>
    @else
        <p class="text-muted mb-0">Select a staff member to view their salary wallet and payment history.</p>
    @endif
</div></section>
