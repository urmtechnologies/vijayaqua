@extends('layouts.admin')

@section('title', 'Add Sale')
@section('page-title', 'Add Sale')
@section('page-action')<a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">Back to Sales</a>@endsection

@section('content')
@include('admin.sales.partials.form', ['sale' => null, 'paid' => 0])
@endsection

@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/sales-form.js') }}" defer></script>@endpush
