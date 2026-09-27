@extends('layouts.admin')
@section('title', 'Edit Upcoming Order')
@section('page-title', 'Edit Upcoming Order #'.$order->id)
@section('page-action')<a href="{{ route('upcoming-orders.show', $order) }}" class="btn btn-outline-secondary">Back to Order</a>@endsection
@section('content')@include('admin.upcoming-orders.partials.form')@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/stock.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/operations.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/upcoming-order-form.js') }}" defer></script>@endpush
