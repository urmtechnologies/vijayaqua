@extends('layouts.admin')

@section('title', 'Edit Stock Entry #'.$entry->id)
@section('page-title', 'Edit Stock Entry #'.$entry->id)
@section('page-action')
    <a href="{{ route('stock-entries.show', $entry) }}" class="btn btn-outline-secondary">Back to Entry</a>
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
