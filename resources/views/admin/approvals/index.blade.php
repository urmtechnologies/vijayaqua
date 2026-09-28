@extends('layouts.admin')
@section('title', 'Approvals')
@section('page-title', 'Pending Approvals')
@section('content')
<div class="card"><div class="card-body">
    <p class="text-muted">Review staff entries before they affect stock, payments, or salary. Approved staff entries become read only for staff.</p>
    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-md-5"><label class="form-label" for="module">Module</label><select id="module" name="module" class="form-select">
            <option value="">All modules</option>
            @foreach(config('operations.modules') as $key => $label)
                @if(!in_array($key, ['stock', 'salaries'], true))<option value="{{ $key }}" @selected(request('module') === $key)>{{ $label }}</option>@endif
            @endforeach
        </select></div><div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
    </form>
    @forelse($groups as $module => $records)
        @if($records->count() > 0)
            <h5>{{ config('operations.modules.'.$module, ucfirst($module)) }} <span class="badge bg-secondary">{{ $records->count() }}</span>
                @unless(request()->filled('module'))<a class="btn btn-sm btn-outline-primary ms-2" href="{{ route('approvals.index', ['module' => $module]) }}">View all</a>@endunless
            </h5>
            <div class="table-responsive mb-4"><table class="table table-sm align-middle"><thead><tr><th>Record</th><th>Added by</th><th>Submitted</th><th>Action</th></tr></thead><tbody>
            @foreach($records as $record)
                <tr><td>#{{ $record->id }} @if($module === 'attendance') · {{ $record->employee?->name }} · {{ $record->work_date->format('d M Y') }} @endif
                    @if($module === 'sales') · {{ $record->invoice_no }} @endif</td>
                    <td>{{ $record->creator?->name ?? 'System' }}</td><td>{{ $record->created_at->format('d M Y, h:i A') }}</td>
                    <td><form method="POST" action="{{ route('approvals.approve', ['module' => $module, 'id' => $record->id]) }}" data-safe-submit>@csrf
                        <button class="btn btn-sm btn-success" data-submit-button><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span> <span data-submit-label>Approve</span></button>
                    </form></td></tr>
            @endforeach
            </tbody></table></div>
            @if(request()->filled('module'))@include('shared.pagination', ['paginator' => $records, 'label' => 'Approval pages'])@endif
        @endif
    @empty
        <p class="text-muted">No pending entries.</p>
    @endforelse
    @if(collect($groups)->every(fn ($records) => $records->count() === 0))<p class="text-muted">No pending entries.</p>@endif
</div></div>
@endsection
@push('scripts')<script src="{{ asset('assets/js/record-form.js') }}" defer></script>@endpush
