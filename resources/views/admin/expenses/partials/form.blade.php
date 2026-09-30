<form method="POST" action="{{ $expense ? route('expenses.update', $expense) : route('expenses.store') }}" id="expenseForm">
    @csrf
    @if($expense) @method('PUT') @endif
    <div class="card">
        <div class="card-header"><h4 class="card-title mb-0">{{ $expense ? 'Edit Expense' : 'New Expense' }}</h4></div>
        <div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label" for="expenseTitle">Title <span class="text-danger">*</span></label>
                <input id="expenseTitle" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $expense?->title) }}" maxlength="150" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label class="form-label" for="expenseCategory">Category <span class="text-danger">*</span></label>
                <select id="expenseCategory" name="expense_category_id" class="form-select @error('expense_category_id') is-invalid @enderror" required>
                    <option value="">Select category</option>
                    @foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('expense_category_id', $expense?->expense_category_id) === (string) $category->id)>{{ $category->name }}</option>@endforeach
                </select>
                @error('expense_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if(auth()->user()->role === 'admin')<a class="small" href="{{ route('expense-categories.index') }}">Manage categories</a>@endif
            </div>
            <div class="col-md-6"><label class="form-label" for="expenseAmount">Amount (₹) <span class="text-danger">*</span></label>
                <input type="number" id="expenseAmount" name="amount_rupees" class="form-control @error('amount_rupees') is-invalid @enderror" min="1" step="1" value="{{ old('amount_rupees', $expense?->amount_rupees) }}" required>
                @error('amount_rupees')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label class="form-label" for="expenseDate">Date <span class="text-danger">*</span></label>
                <input type="date" id="expenseDate" name="expense_date" class="form-control @error('expense_date') is-invalid @enderror" value="{{ old('expense_date', $expense?->expense_date?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12"><label class="form-label" for="expenseNotes">Notes</label>
                <textarea id="expenseNotes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4" maxlength="3000">{{ old('notes', $expense?->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2"><a href="{{ route('expenses.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary" id="saveExpense"><span class="spinner-border spinner-border-sm me-1 d-none" id="expenseSpinner" aria-hidden="true"></span><span id="expenseButtonText">{{ $expense ? 'Save Changes' : 'Add Expense' }}</span></button>
        </div>
    </div>
</form>
