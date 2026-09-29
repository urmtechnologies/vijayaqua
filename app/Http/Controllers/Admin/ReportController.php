<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\{ReportCatalog, ReportPdf, ReportXlsx};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $visible = ReportCatalog::visible();
        $request->validate(['module' => ['nullable', 'string', Rule::in(array_keys(ReportCatalog::LABELS))]]);
        $selected = $request->query('module') ?: array_key_first($visible);
        if ($selected) ReportCatalog::authorize($selected);
        $filters = $this->filters($request);
        $query = $selected ? ReportCatalog::query($selected, $filters) : null;
        $summary = $query ? ReportCatalog::summary($selected, clone $query, $filters) : null;
        $records = $query?->paginate(25)->withQueryString();
        $headings = $selected ? ReportCatalog::headings($selected) : [];
        $rows = $records?->getCollection()->map(fn ($record) => ReportCatalog::row($selected, $record)) ?? collect();

        return view('admin.reports.index', compact('visible', 'selected', 'filters', 'records', 'headings', 'rows', 'summary'));
    }

    public function export(Request $request, string $module, string $format): Response
    {
        ReportCatalog::authorize($module);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $filters = $this->filters($request);
        $query = ReportCatalog::query($module, $filters);
        $limit = $format === 'pdf' ? 1000 : 10000;
        if ((clone $query)->count() > $limit) {
            throw ValidationException::withMessages(['export' => "More than {$limit} rows match. Narrow the date or search filters before exporting."]);
        }
        $rows = $query->get()->map(fn ($record) => ReportCatalog::row($module, $record))->all();
        $headings = ReportCatalog::headings($module);
        $label = ReportCatalog::LABELS[$module];
        if ($format === 'xlsx') {
            $content = ReportXlsx::make($headings, $rows);
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            $parts = array_filter([
                ! empty($filters['month']) ? 'Month: '.$filters['month'] : null,
                ! empty($filters['from']) ? 'From: '.$filters['from'] : null,
                ! empty($filters['to']) ? 'To: '.$filters['to'] : null,
                ! empty($filters['search']) ? 'Search: '.$filters['search'] : null,
                'Records: '.count($rows),
            ]);
            $content = ReportPdf::make($label, $headings, $rows, implode('  |  ', $parts));
            $mime = 'application/pdf';
        }
        $filename = str_replace(' ', '-', strtolower($label)).'-'.now()->format('Ymd-His').'.'.$format;
        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'month' => ['nullable', 'date_format:Y-m'],
            'approval' => ['nullable', Rule::in(['pending', 'approved'])],
            'type' => ['nullable', Rule::in(['full', 'custom', 'leave', 'manufacture', 'purchase', 'send', 'receive'])],
            'status' => ['nullable', Rule::in(['paid', 'due', 'settled', 'active', 'inactive'])],
            'method' => ['nullable', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'availability' => ['nullable', Rule::in(['available', 'empty'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name', 'soonest', 'latest'])],
            'category' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'role' => ['nullable', 'string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
