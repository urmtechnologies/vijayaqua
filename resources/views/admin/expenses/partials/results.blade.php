<div class="row g-3 mb-4">
    <div class="col-sm-6"><div class="va-ledger-metric"><small>Matching expenses</small><strong>{{ \App\Support\CartonNumber::format($summary['count']) }}</strong></div></div>
    <div class="col-sm-6"><div class="va-ledger-metric"><small>Approved amount</small><strong>₹{{ \App\Support\RupeeAmount::format($summary['amount']) }}</strong></div></div>
</div>
<div class="table-responsive"><table class="table align-middle mb-0">
    <thead class="table-light"><tr><th>Date</th><th>Title</th><th>Category</th><th>Amount</th><th>Notes</th><th>Added by</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    @forelse($expenses as $expense)
        <tr>
            <td>{{ $expense->expense_date->format('d M Y') }}</td>
            <td><strong>{{ $expense->title }}</strong> @include('shared.approval-status', ['record' => $expense])</td>
            <td>{{ $expense->category->name }}</td>
            <td>₹{{ \App\Support\RupeeAmount::format($expense->amount_rupees) }}</td>
            <td class="va-expense-note" title="{{ $expense->notes }}">{{ $expense->notes ?: '—' }}</td>
            <td>{{ $expense->creator?->name ?? 'System' }}@if($expense->updated_by)<br><small class="text-muted">Edited by {{ $expense->editor?->name ?? 'System' }}</small>@endif</td>
            <td class="text-end text-nowrap">@if(\App\Support\Access::canEdit('expenses', $expense))<a href="{{ route('expenses.edit', $expense) }}" class="btn btn-sm btn-outline-secondary">Edit</a>@endif
                @if(\App\Support\Access::canDelete('expenses', $expense))<form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="d-inline">@csrf @method('DELETE')
                    <button type="button" class="btn btn-sm btn-outline-danger" data-va-delete data-va-delete-name="{{ $expense->title }}">Delete</button>
                </form>@endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted py-5">No expenses found.</td></tr>
    @endforelse
    </tbody>
</table></div>
@include('shared.pagination', ['paginator' => $expenses, 'label' => 'Expenses pagination'])
