<div class="va-vehicle-accounts">
    @forelse($accounts as $account)
        @php $wallet = $account->wallet; @endphp
        <a class="va-vehicle-account" href="{{ route('vehicle-entries.account', $account) }}">
            <span><strong>{{ $account->name }}</strong><small>{{ $account->mobile }} · {{ $account->entry_count }} entries</small></span>
            <span class="va-vehicle-account-figures"><span><small>Approved total</small>₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($wallet['charged'])) }}</span>
                <span><small>Paid</small>₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($wallet['paid'])) }}</span>
                <span class="{{ $wallet['balance'] > 0 ? 'text-danger' : 'text-success' }}"><small>Balance</small>₹{{ \App\Support\SaleMoney::format(\App\Support\SaleMoney::decimal($wallet['balance'])) }}</span></span>
            <i class="mdi mdi-chevron-right" aria-hidden="true"></i>
        </a>
    @empty
        <div class="text-center text-muted py-5">No vehicle entries yet. Add an entry to start a user account.</div>
    @endforelse
</div>
@include('shared.pagination', ['paginator' => $accounts, 'label' => 'Vehicle accounts pagination'])
