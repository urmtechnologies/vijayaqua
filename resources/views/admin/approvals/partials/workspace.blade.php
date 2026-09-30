<nav class="va-approval-tabs" aria-label="Pending approval modules">
    <a href="{{ route('approvals.index') }}" class="va-approval-tab {{ $selected === '' ? 'active' : '' }}" @if($selected === '') aria-current="page" @endif>All <span class="va-approval-count">{{ array_sum($counts) }}</span></a>
    @foreach($counts as $module => $count)
    <a href="{{ route('approvals.index', ['module' => $module]) }}" class="va-approval-tab {{ $selected === $module ? 'active' : '' }}" @if($selected === $module) aria-current="page" @endif>
        {{ config('operations.modules.'.$module, ucfirst($module)) }} <span class="va-approval-count {{ $count ? 'has-pending' : '' }}">{{ $count }}</span>
    </a>
    @endforeach
</nav>
<div class="va-approval-feedback" role="status" hidden></div>
@php $hasRows = false; @endphp
@foreach($groups as $module => $records)
    @if($records->count())
        @php $hasRows = true; @endphp
        <section class="mb-4" aria-label="{{ config('operations.modules.'.$module, ucfirst($module)) }} approvals">
            @if($selected === '')<h5 class="d-flex justify-content-between align-items-center">{{ config('operations.modules.'.$module, ucfirst($module)) }}
                <a href="{{ route('approvals.index', ['module' => $module]) }}" class="small">View {{ $counts[$module] }} pending</a></h5>@endif
            <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Record</th><th>Added by</th><th>Submitted</th><th class="text-end">Action</th></tr></thead><tbody>
            @foreach($records as $record)
                <tr><td>#{{ $record->id }}
                    @if($module === 'attendance') · {{ $record->employee?->name }} · {{ $record->work_date->format('d M Y') }} @endif
                    @if($module === 'sales') · {{ $record->invoice_no }} @endif
                    @if($module === 'vehicle-entries') · {{ $record->referenceUser?->name }} · {{ $record->from_destination }} → {{ $record->to_destination }} @endif
                    </td><td>{{ $record->creator?->name ?? 'System' }}</td><td>{{ $record->created_at->format('d M Y, h:i A') }}</td>
                    <td class="text-end"><form method="POST" action="{{ route('approvals.approve', ['module' => $module, 'id' => $record->id]) }}" class="va-approval-form">@csrf
                        <button class="btn btn-sm btn-success" type="submit">Approve</button>
                    </form></td></tr>
            @endforeach
            </tbody></table></div>
            @if($selected !== '')@include('shared.pagination', ['paginator' => $records, 'label' => 'Approval pages'])@endif
        </section>
    @endif
@endforeach
@unless($hasRows)<p class="text-muted py-4 text-center mb-0">No pending entries here.</p>@endunless
