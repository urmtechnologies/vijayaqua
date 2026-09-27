@extends('layouts.admin')
@section('title', 'Add Partner Entry')
@section('page-title', 'Add Partner Entry')
@section('page-action')<a href="{{ route('partner-ledger.index') }}" class="btn btn-outline-secondary">Back to Ledger</a>@endsection
@section('content')<div class="row"><div class="col-xl-9">@include('admin.partner-ledger.partials.form')</div></div>@endsection
@push('scripts')<script src="{{ asset('assets/js/record-form.js') }}" defer></script>@endpush
