<div class="row g-2 mb-3">
    <div class="col-6 col-xl-3"><div class="va-ledger-metric"><small>Parties</small><strong>{{ number_format($summary['parties']) }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="va-ledger-metric"><small>Approved sales</small><strong>₹{{ \App\Support\SaleMoney::format($summary['billed']) }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="va-ledger-metric"><small>Received</small><strong>₹{{ \App\Support\SaleMoney::format($summary['received']) }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="va-ledger-metric"><small>Balance</small><strong>₹{{ \App\Support\SaleMoney::format($summary['balance']) }}</strong></div></div>
</div>
<div class="table-responsive va-party-table">
<table class="table align-middle mb-0">
<thead class="table-light"><tr><th>Party</th><th>Sales</th><th>Billed</th><th>Received</th><th>Balance</th><th>Status</th><th class="text-end">Open</th></tr></thead>
<tbody>
@forelse($customers as $customer)
@php $w = $wallets[$customer->id]; @endphp
<tr>
    <td data-label="Party"><strong>{{ $customer->name }}</strong><small class="d-block text-muted">{{ $customer->mobile }}</small></td>
    <td data-label="Sales">{{ $counts[$customer->id] }}@if($pending[$customer->id]) <span class="va-status-icon va-status-pending" title="{{ $pending[$customer->id] }} sale(s) waiting for approval" aria-label="Pending approval"><i class="ri-time-line" aria-hidden="true"></i></span>@endif</td>
    <td data-label="Billed">₹{{ \App\Support\SaleMoney::format($w['billed']) }}</td>
    <td data-label="Received">₹{{ \App\Support\SaleMoney::format($w['paid']) }}</td>
    <td data-label="Balance"><strong>₹{{ \App\Support\SaleMoney::format($w['balance']) }}</strong></td>
    <td data-label="Status">@if($pending[$customer->id]) <span class="va-status-icon va-status-pending" title="Pending approval" aria-label="Pending approval"><i class="ri-time-line" aria-hidden="true"></i></span> @endif @if($drafts[$customer->id]) <span class="va-status-icon va-status-draft" title="Draft sale" aria-label="Draft sale"><i class="ri-draft-line" aria-hidden="true"></i></span> @endif @if(!$pending[$customer->id] && !$drafts[$customer->id]) <span class="va-status-icon va-status-approved" title="Approved" aria-label="Approved"><i class="ri-checkbox-circle-line" aria-hidden="true"></i></span> @endif</td>
    <td data-label="Open" class="text-end"><a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-outline-primary">Manage <i class="ri-arrow-right-line ms-1"></i></a></td>
</tr>
@empty
<tr><td colspan="7" class="text-center py-5 text-muted">No parties found. Add a sale to get started.</td></tr>
@endforelse
</tbody></table></div>
@include('shared.pagination', ['paginator' => $customers, 'label' => 'Party sales pagination'])
