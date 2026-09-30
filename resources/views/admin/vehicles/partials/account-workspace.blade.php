<div class="va-vehicle-feedback alert" role="status" hidden></div>
<div class="card va-vehicle-card mb-3"><div class="card-body va-vehicle-header">
    <div><span class="text-muted small text-uppercase">Added by</span><h4 class="mb-1">{{ $user->name }}</h4><span class="text-muted">{{ $user->mobile }} · {{ ucfirst($user->role) }}</span></div>
    <div class="va-vehicle-wallet"><span><small>Total Payment</small><strong>₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($wallet['charged'])) }}</strong></span>
        <span class="text-success"><small>Total Paid</small><strong>₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($wallet['paid'])) }}</strong></span>
        <span class="{{ $wallet['balance'] > 0 ? 'text-danger' : 'text-success' }}"><small>Balance</small><strong>₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($wallet['balance'])) }}</strong></span></div>
</div></div>
<div class="card va-vehicle-card mb-3"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h5 class="mb-0">Vehicle entries</h5>@if($pendingCount)<span class="text-warning small">{{ $pendingCount }} pending approval</span>@endif</div>
    @forelse($entries as $entry)
        <article class="va-vehicle-trip" id="vehicleEntry{{ $entry->id }}">
            <div class="va-vehicle-trip-main"><span class="text-muted small">{{ $entry->entry_date->format('d M Y') }} · #{{ $entry->id }}</span>
                <strong>{{ $entry->from_destination }} <span aria-hidden="true">→</span> {{ $entry->to_destination }}</strong>
                <div class="text-muted small">@foreach($entry->items as $item){{ $item->product_name }} × {{ $item->cartons }} CTN{{ !$loop->last ? ' · ' : '' }}@endforeach</div>
                <small>Reference: {{ $entry->referenceUser?->name ?? 'Deleted user' }}</small></div>
            <div class="va-vehicle-trip-side"><strong>₹{{ \App\Support\SaleMoney::format($entry->amount_rupees) }}</strong>
                @include('shared.approval-status', ['record' => $entry])
                <div class="d-flex gap-2 flex-wrap">
                @if(auth()->user()->role === 'admin' && $entry->approval_status === 'pending')<form method="POST" action="{{ route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]) }}" data-vehicle-approve>@csrf<button class="btn btn-sm btn-success">Approve</button></form>@endif
                @if(\App\Support\Access::canEdit('vehicle-entries', $entry))<a href="{{ route('vehicle-entries.edit', $entry) }}" class="btn btn-sm btn-outline-primary">Edit</a>@endif
                @if(\App\Support\Access::canDelete('vehicle-entries', $entry))<form method="POST" action="{{ route('vehicle-entries.destroy', $entry) }}" onsubmit="return confirm('Delete this vehicle entry?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endif</div></div>
        </article>
    @empty <p class="text-muted">No entries yet.</p>
    @endforelse
    @include('shared.pagination', ['paginator' => $entries, 'label' => 'Vehicle entries pagination'])
</div></div>
<div class="card va-vehicle-card"><div class="card-body"><h5>Wallet payment</h5>
    <p class="text-muted small">Credit = paid · Debit = returned · Pending entries are excluded.</p>
    @if(auth()->user()->role === 'admin' && ! $user->trashed())
    <form method="POST" action="{{ route('vehicle-entries.payment', $user) }}" class="va-vehicle-payment-form row g-2 align-items-end mb-4" data-vehicle-payment>@csrf
        <div class="col-6 col-lg-2"><label class="form-label" for="walletDate">Date</label><input type="date" name="payment_date" id="walletDate" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="walletType">Entry</label><select name="entry_type" id="walletType" class="form-select"><option value="credit" @selected(old('entry_type', 'credit') === 'credit')>Credit · pay</option><option value="debit" @selected(old('entry_type') === 'debit')>Debit · returned</option></select></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="walletAmount">Amount (₹)</label><input name="amount_rupees" id="walletAmount" type="number" step="0.01" min="0.01" class="form-control" value="{{ old('amount_rupees') }}" required></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="walletMethod">Method</label><select name="method" id="walletMethod" class="form-select"><option value="cash" @selected(old('method', 'cash') === 'cash')>Cash</option><option value="upi" @selected(old('method', 'cash') === 'upi')>UPI</option><option value="bank" @selected(old('method', 'cash') === 'bank')>Bank</option><option value="other" @selected(old('method', 'cash') === 'other')>Other</option></select></div>
        <div class="col-12 col-lg-2"><label class="form-label" for="walletNote">Note</label><input name="note" id="walletNote" maxlength="500" value="{{ old('note') }}" class="form-control"></div>
        <div class="col-12 col-lg-2"><button class="btn btn-primary w-100" data-submit-button>Record Payment</button></div>
    </form>
    @endif
    <h6 class="mt-4">Payment history</h6>
    @forelse($payments as $payment)
        <div class="va-vehicle-payment"><span><strong>{{ $payment->payment_date->format('d M Y') }}</strong><small>{{ ucfirst($payment->method) }} · {{ $payment->creator?->name ?? 'Admin' }}{{ $payment->note ? ' · '.$payment->note : '' }}</small></span>
            <span class="{{ $payment->entry_type === 'credit' ? 'text-success' : 'text-danger' }}">{{ $payment->entry_type === 'credit' ? 'Credit' : 'Debit' }} ₹{{ \App\Support\SaleMoney::format($payment->amount_rupees) }}</span></div>
    @empty <p class="text-muted py-3">No payments yet.</p>
    @endforelse
    @include('shared.pagination', ['paginator' => $payments, 'label' => 'Vehicle payment pages'])
</div></div>
