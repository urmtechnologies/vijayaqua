<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <div class="va-ledger-metric"><small>Matching
                orders</small><strong>{{ \App\Support\CartonNumber::format($summary['orders']) }}</strong></div>
    </div>
    <div class="col-sm-6">
        <div class="va-ledger-metric"><small>Ordered
                CTN</small><strong>{{ \App\Support\CartonNumber::format($summary['cartons']) }}</strong></div>
    </div>
</div>
<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Scheduled Date</th>
                <th>Products</th>
                <th>Qty (CTN)</th>
                <th>Added by</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                <tr>
                    <td><strong>#{{ $order->id }}</strong> @include('shared.approval-status', ['record' => $order])</td>
                    <td>{{ $order->customer->name ?? '' }}<br><small
                            class="text-muted">{{ $order->customer->mobile ?? '' }}</small></td>
                    <td>{{ $order->scheduled_date->format('d M Y') }}@if ($order->scheduled_date->lt(today()))
                            <br><span class="badge bg-warning text-dark">Past date</span>
                        @endif
                    </td>
                    <td>{{ $order->items_count }}</td>
                    <td><strong>{{ \App\Support\CartonNumber::format($order->carton_total) }}</strong></td>
                    <td>{{ $order->creator?->name ?? 'System' }}@if ($order->updated_by)
                            <br><small class="text-muted">Edited by {{ $order->editor?->name ?? 'System' }}</small>
                        @endif
                    </td>
                    <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary"
                            href="{{ route('upcoming-orders.show', $order) }}">View</a>
                        @if (\App\Support\Access::canEdit('upcoming-orders', $order))
                            <a class="btn btn-sm btn-outline-secondary"
                                href="{{ route('upcoming-orders.edit', $order) }}">Edit</a>
                        @endif
                        @if (\App\Support\Access::canDelete('upcoming-orders', $order))
                            <form method="POST" action="{{ route('upcoming-orders.destroy', $order) }}"
                                class="d-inline">@csrf @method('DELETE')
                                <button type="button" class="btn btn-sm btn-outline-danger" data-va-delete
                                    data-va-delete-name="Upcoming Order #{{ $order->id }}">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">No upcoming orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('shared.pagination', ['paginator' => $orders, 'label' => 'Upcoming orders pagination'])
