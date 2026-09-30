@extends('layouts.admin')
@section('title', 'Add Partner Entry')
@section('page-title', 'Add Partner Entry')
@section('page-action')<a href="{{ route('partner-ledger.index') }}" class="btn btn-outline-secondary">Back to Ledger</a>@endsection
@section('content')<div class="row"><div class="col-xl-9">@include('admin.partner-ledger.partials.form')</div></div>@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/partner-attachments.css') }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/record-form.js') }}" defer></script><script src="{{ asset('assets/js/partner-attachments.js') }}" defer></script>@endpush
