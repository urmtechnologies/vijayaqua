@extends('layouts.admin')
@section('title', 'Expense Categories')
@section('page-title', 'Expense Categories')
@section('page-action')<a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">Back to Expenses</a>@endsection
@section('content')
<div class="row g-3">
    <div class="col-lg-4"><div class="card"><div class="card-body">
        <h5>Add Category</h5>
        <form action="{{ route('expense-categories.store') }}" method="POST" data-safe-submit>@csrf
            <label for="categoryName" class="form-label">Name</label>
            <input id="categoryName" name="name" value="{{ old('name') }}" class="form-control" maxlength="80" required>
            <button class="btn btn-primary mt-3" data-submit-button>Add Category</button>
        </form>
    </div></div></div>
    <div class="col-lg-8"><div class="card"><div class="card-body"><h5>Categories</h5>
        @forelse($categories as $category)
            <div class="d-flex gap-2 align-items-center flex-wrap border-bottom py-2">
                <form action="{{ route('expense-categories.update', $category) }}" method="POST" class="d-flex gap-2 align-items-center flex-grow-1">@csrf @method('PUT')
                    <input class="form-control" name="name" value="{{ $category->name }}" aria-label="Category name" maxlength="80" required>
                    <span class="text-muted text-nowrap">{{ $category->expenses_count }} expenses</span>
                    <button class="btn btn-sm btn-outline-primary">Save</button>
                </form>
                @if(!$category->expenses_count)<form method="POST" action="{{ route('expense-categories.destroy', $category) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endif
            </div>
        @empty <p class="text-muted">Add your first category to organize expenses.</p>
        @endforelse
    </div></div></div>
</div>
@endsection
@push('scripts')<script src="{{ asset('assets/js/record-form.js') }}" defer></script>@endpush
