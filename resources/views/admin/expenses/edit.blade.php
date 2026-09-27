@extends('layouts.admin')
@section('title', 'Edit Expense')
@section('page-title', 'Edit Expense')
@section('page-action')<a class="btn btn-outline-secondary" href="{{ route('expenses.index') }}">Back to Expenses</a>@endsection
@section('content')<div class="row"><div class="col-xl-9">@include('admin.expenses.partials.form')</div></div>@endsection
@push('scripts')<script src="{{ asset('assets/js/expense-form.js') }}" defer></script>@endpush
