@extends('layouts.admin')

@section('title', 'Edit '.$sale->invoice_no)
@section('page-title', 'Edit '.$sale->invoice_no)
@section('page-action')<a href="{{ route('sales.show', $sale) }}" class="btn btn-outline-secondary">Back to Invoice</a>@endsection

@section('content')
@include('admin.sales.partials.form')
@endsection

@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/sales-form.js') }}" defer></script>@endpush
