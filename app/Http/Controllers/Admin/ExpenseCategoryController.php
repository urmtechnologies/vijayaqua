<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.expenses.categories', [
            'categories' => ExpenseCategory::withCount(['expenses' => fn ($query) => $query->withTrashed()])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = $this->name($request);
        ExpenseCategory::create(['name' => $name, 'key' => Str::lower($name)]);

        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $name = $this->name($request, $category);
        $category->update(['name' => $name, 'key' => Str::lower($name)]);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        if ($category->expenses()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['category' => 'This category has expense history and cannot be deleted.']);
        }
        $category->delete();

        return back()->with('success', 'Category removed.');
    }

    private function name(Request $request, ?ExpenseCategory $category = null): string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $request->input('name')));
        $request->merge(['name' => $name]);
        $request->validate(['name' => ['required', 'string', 'max:80']]);
        if (ExpenseCategory::where('key', Str::lower($name))->where('id', '!=', $category?->id ?? 0)->exists()) {
            throw ValidationException::withMessages(['name' => 'This category already exists.']);
        }

        return $name;
    }
}
