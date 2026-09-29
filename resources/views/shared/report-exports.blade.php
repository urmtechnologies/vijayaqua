@if(\App\Support\Access::allowed($reportModule) && ($reportModule !== 'stock' || \App\Support\Access::all('stock')))
<span class="d-inline-flex flex-wrap gap-1 me-2">
    <a class="btn btn-outline-success btn-sm" href="{{ route('reports.export', array_merge(['module' => $reportModule, 'format' => 'xlsx'], request()->only(['search', 'from', 'to', 'month', 'approval', 'type', 'status', 'method', 'availability', 'sort', 'category', 'role']))) }}" aria-label="Download {{ $reportModule }} Excel report"><i class="mdi mdi-file-excel-outline me-1"></i> Excel</a>
    <a class="btn btn-outline-danger btn-sm" href="{{ route('reports.export', array_merge(['module' => $reportModule, 'format' => 'pdf'], request()->only(['search', 'from', 'to', 'month', 'approval', 'type', 'status', 'method', 'availability', 'sort', 'category', 'role']))) }}" aria-label="Download {{ $reportModule }} PDF report"><i class="mdi mdi-file-pdf-box me-1"></i> PDF</a>
</span>
@endif
