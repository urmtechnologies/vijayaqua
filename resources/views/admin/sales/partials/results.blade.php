<div class="row g-2 mb-3">
    <div class="col-6 col-xl-3">
        <div class="va-ledger-metric"><small>Sales</small><strong>{{ number_format($summary['sales']) }}</strong></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="va-ledger-metric"><small>Approved
                total</small><strong>₹{{ \App\Support\SaleMoney::format($summary['billed']) }}</strong></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="va-ledger-metric"><small>Net
                received</small><strong>₹{{ \App\Support\SaleMoney::format($summary['received']) }}</strong></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="va-ledger-metric">
            <small>Due</small><strong>₹{{ \App\Support\SaleMoney::format($summary['balance']) }}</strong>
        </div>
    </div>
</div>
<div class="table-responsive va-party-table">
    <table class="table align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Customer</th>
                {{-- <th>Sales in this group</th> --}}
                <th>Total sales</th>
                <th>Received</th>
                <th>Due</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $customer)
                @php $totals = $customer->workspace_totals; @endphp
                <tr>
                    <td data-label="Customer"><strong>{{ $customer->name }}</strong><small
                            class="d-block text-muted">{{ $customer->mobile }}@if ($customer->business_name)
                                · {{ $customer->business_name }}
                            @endif

                        </small>
                    </td>
                    {{-- <td data-label="Sales in this group">
                        @forelse($customer->visibleSales as $entry)
                            <span class="d-inline-block me-2">{{ $entry->invoice_no }}@if ($entry->is_draft)
                                    <small class="text-muted">Unfinished</small>
                                @elseif($entry->approval_status === 'pending')
                                    <small class="text-warning">Pending</small>
                                @endif
                            </span>
                            @empty <span class="text-muted">No sales yet</span>
                            @endforelse
                        </td> --}}
                    <td data-label="Total sales">₹{{ \App\Support\SaleMoney::format($totals['billed']) }}</td>
                    <td data-label="Received" class="text-success">
                        ₹{{ \App\Support\SaleMoney::format($totals['paid']) }}</td>
                    <td data-label="Due" class="text-danger">₹{{ \App\Support\SaleMoney::format($totals['balance']) }}
                    </td>
                    <td data-label="Action" class="text-end"><a href="{{ route('customers.show', $customer) }}"
                            class="btn btn-sm btn-outline-primary">View Sales</a></td>
                </tr>
                @empty <tr>
                        <td colspan="6" class="text-center text-muted py-5">No customers found. Use Add Sale to start.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('shared.pagination', ['paginator' => $customers, 'label' => 'Customers pagination'])
