@extends('layouts.admin')
@section('title', 'Add Upcoming Order')
@section('page-title', 'Add Upcoming Order')
@section('page-action')<a href="{{ route('upcoming-orders.index') }}" class="btn btn-outline-secondary">Back to Orders</a>@endsection
@section('content')@include('admin.upcoming-orders.partials.form')@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/stock.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/operations.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/upcoming-order-form.js') }}" defer></script>@endpush
