@extends('layouts.admin')

@section('title', 'Add Stock Entry')
@section('page-title', 'Add Stock Entry')
@section('page-action')
    <a href="{{ route('stock-entries.index') }}" class="btn btn-outline-secondary">Back to Stock List</a>
@endsection

@section('content')
    @include('admin.stock.partials.form')
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/stock.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/stock-form.js') }}" defer></script>
@endpush
