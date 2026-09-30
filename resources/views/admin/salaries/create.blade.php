@extends('layouts.admin')
@section('title', 'Add Salary')
@section('page-title', 'Add Salary')
@section('content')
<div class="va-staff-workspace">
    <a href="{{ route('attendance.index', ['month' => $month]) }}" class="btn btn-link px-0 mb-2">← Staff calendar</a>
    @include('admin.attendance.partials.wallet')
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/attendance-calendar.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/record-form.js') }}" defer></script>@endpush
