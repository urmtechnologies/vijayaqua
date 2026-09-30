<div class="row g-2 mb-3">
    @foreach(['Monthly rate' => $monthlyPaise, 'Earned this month' => $earned, 'Previous balance' => $carry, 'Credit minus returns this month' => $advance, 'Paid this month' => $paid, 'Balance through month' => $due] as $label => $value)
        <div class="col-6 col-lg-4"><div class="va-people-metric"><small>{{ $label }}</small><strong>₹{{ \App\Support\SalaryMath::format($value) }}</strong></div></div>
    @endforeach
</div>
@if($existing)<div class="alert alert-info">Salary was already generated for {{ $start->format('F Y') }}. <a href="{{ route('salaries.show', $existing) }}">Open salary</a>.</div>@endif
@if($pendingCount)<div class="alert alert-warning">{{ $pendingCount }} attendance entry/entries are waiting for admin approval. Approve them before generating salary.</div>@endif
<div class="table-responsive va-salary-days"><table class="table table-sm align-middle"><thead class="table-light"><tr><th>Date</th><th>Attendance</th><th>Hours</th><th>Approval</th><th class="text-end">Earned</th></tr></thead><tbody>
@foreach($days as $day)
    @php $record = $day['record']; @endphp
    <tr><td>{{ $day['date']->format('d M, D') }}</td>
        <td>{{ $record ? ucfirst($record->type) : 'No entry' }}</td>
        <td>{{ $record && $record->approval_status === 'approved' ? number_format($record->minutes / 60, 2) : '0' }}</td>
        <td>@if($record) @include('shared.approval-status', ['record' => $record]) @else — @endif</td>
        <td class="text-end">₹{{ \App\Support\SalaryMath::format($day['earned']) }}</td></tr>
@endforeach
</tbody></table></div>
<p class="text-muted small">Attendance and monthly rate are fixed when salary is generated. Later advances and payments update the balance.</p>
