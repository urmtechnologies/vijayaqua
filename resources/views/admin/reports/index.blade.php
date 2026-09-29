@extends('layouts.admin')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('content')
    <div class="va-report-intro mb-4">
        <div>
            <span class="va-report-eyebrow">WORKSPACE REPORTS</span>
            <h3 class="mb-2 text-white">One place for every record</h3>
            <p class="mb-0">Choose a module, apply filters and download the same visible data. Pending entries are marked;
                totals count approved entries.</p>
        </div>
        @if ($selected)
            <div class="va-report-downloads">
                <a class="btn btn-light"
                    href="{{ route('reports.export', array_merge(['module' => $selected, 'format' => 'xlsx'], $filters)) }}"><i
                        class="mdi mdi-file-excel-outline me-1"></i> Excel</a>
                <a class="btn btn-outline-light"
                    href="{{ route('reports.export', array_merge(['module' => $selected, 'format' => 'pdf'], $filters)) }}"><i
                        class="mdi mdi-file-pdf-box me-1"></i> PDF</a>
            </div>
        @endif
    </div>

    @if (!$selected)
        <div class="card">
            <div class="card-body">No reports are available for your account. Ask an admin for view permission.</div>
        </div>
    @else
        <div class="va-report-tabs mb-3" aria-label="Report modules">
            @foreach ($visible as $key => $label)
                <a href="{{ route('reports.index', ['module' => $key]) }}"
                    class="va-report-tab {{ $selected === $key ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end">
                    <input type="hidden" name="module" value="{{ $selected }}">
                    <div class="col-md-4"><label class="form-label" for="reportSearch">Search</label><input
                            id="reportSearch" name="search" type="search" maxlength="100" value="{{ request('search') }}"
                            class="form-control" placeholder="Name, mobile, product or invoice"></div>
                    @if ($selected !== 'stock')
                        <div class="col-sm-6 col-md-2"><label class="form-label" for="reportFrom">From</label><input
                                id="reportFrom" name="from" type="date" value="{{ request('from') }}"
                                class="form-control"></div>
                        <div class="col-sm-6 col-md-2"><label class="form-label" for="reportTo">To</label><input
                                id="reportTo" name="to" type="date" value="{{ request('to') }}"
                                class="form-control"></div>
                    @endif
                    @if (!in_array($selected, ['stock', 'salaries']))
                        <div class="col-sm-6 col-md-2"><label class="form-label"
                                for="reportApproval">Approval</label><select id="reportApproval" name="approval"
                                class="form-select">
                                <option value="">All</option>
                                <option value="approved" @selected(request('approval') === 'approved')>Approved</option>
                                <option value="pending" @selected(request('approval') === 'pending')>Pending</option>
                            </select></div>
                    @endif
                    <div class="col-sm-6 col-md-auto"><button class="btn btn-primary w-100" type="submit">Show
                            report</button></div>
                    <div class="col-sm-6 col-md-auto"><a href="{{ route('reports.index', ['module' => $selected]) }}"
                            class="btn btn-light w-100">Clear</a></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h4 class="card-title mb-0">{{ $visible[$selected] }}</h4>
                <span class="text-muted small">{{ number_format($records->total()) }} matching records @if ($summary)
                        · {{ $summary['label'] }}: <strong>{{ $summary['value'] }}</strong>
                    @endif
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 va-report-table">
                        <thead class="table-light">
                            <tr>
                                @foreach ($headings as $heading)
                                    <th scope="col">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    @foreach ($row as $value)
                                        <td>{{ $value ?? '—' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($headings) }}" class="text-center text-muted py-5">No records
                                        match your filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($records->hasPages())
                    <div class="p-3 border-top">{{ $records->links() }}</div>
                @endif
            </div>
        </div>
    @endif
@endsection
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/reports.css') }}">
@endpush
