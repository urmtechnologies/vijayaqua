<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\RupeeAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'approval' => ['nullable', Rule::in(['pending', 'approved'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Access::scope(Expense::query(), 'expenses');
        if ($approval = $filters['approval'] ?? null) $query->where('approval_status', $approval);
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('notes', 'like', '%'.$search.'%'));
        }
        if ($category = $filters['category'] ?? null) $query->where('expense_category_id', $category);
        if ($from = $filters['from'] ?? null) $query->whereDate('expense_date', '>=', $from);
        if ($to = $filters['to'] ?? null) $query->whereDate('expense_date', '<=', $to);

        $summary = ['count' => (clone $query)->count(), 'amount' => (string) (clone $query)->where('approval_status', 'approved')->sum('amount_rupees')];
        $query->with(['category', 'creator', 'editor', 'approver']);
        if (($filters['sort'] ?? 'newest') === 'oldest') $query->orderBy('expense_date')->orderBy('id');
        else $query->orderByDesc('expense_date')->orderByDesc('id');
        $expenses = $query->paginate(10)->withQueryString();

        return $request->ajax()
            ? view('admin.expenses.partials.results', compact('expenses', 'summary'))
            : view('admin.expenses.index', [
                'expenses' => $expenses, 'summary' => $summary,
                'categories' => ExpenseCategory::orderBy('name')->get(),
            ]);
    }

    public function create(): View
    {
        return view('admin.expenses.create', ['expense' => null, 'categories' => ExpenseCategory::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data): void {
            $category = $this->category($data['category_name']);
            Expense::create([
                'expense_date' => $data['expense_date'], 'title' => trim($data['title']),
                'expense_category_id' => $category->id,
                'amount_rupees' => RupeeAmount::int($data['amount_rupees'], 'amount_rupees'),
                'notes' => $data['notes'] ?? null,
            ]);
        }, 3);

        return redirect()->route('expenses.index')->with('success', 'Expense added successfully.');
    }

    public function edit(Expense $expense): View
    {
        $expense->load('category');

        return view('admin.expenses.edit', ['expense' => $expense, 'categories' => ExpenseCategory::orderBy('name')->get()]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($expense, $data): void {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canEdit('expenses', $expense), 403);
            $category = $this->category($data['category_name']);
            $expense->update([
                'expense_date' => $data['expense_date'], 'title' => trim($data['title']),
                'expense_category_id' => $category->id,
                'amount_rupees' => RupeeAmount::int($data['amount_rupees'], 'amount_rupees'),
                'notes' => $data['notes'] ?? null,
            ]);
            $expense->forceFill(['updated_by' => auth()->id(), 'updated_at' => now()])->save();
        }, 3);

        return redirect()->route('expenses.index')->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        DB::transaction(function () use ($expense): void {
            $record = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canDelete('expenses', $record), 403);
            $record->delete();
        }, 3);

        return redirect()->route('expenses.index')->with('success', 'Expense removed.');
    }

    private function validated(Request $request): array
    {
        if (is_string($request->input('category_name'))) {
            $request->merge(['category_name' => preg_replace('/\s+/u', ' ', trim($request->input('category_name')))]);
        }

        return $request->validate([
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'title' => ['required', 'string', 'max:150'],
            'category_name' => ['required', 'string', 'max:80'],
            'amount_rupees' => ['required', 'regex:/^[1-9][0-9]{0,15}$/'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function category(string $name): ExpenseCategory
    {
        return ExpenseCategory::createOrFirst(['key' => Str::lower($name)], ['name' => $name]);
    }
}
