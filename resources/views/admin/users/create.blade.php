@extends('layouts.admin')

@section('title', 'New User')
@section('page-title', 'New User')
@section('page-action')
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Back to Users</a>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-8 col-xxl-7">
        <div class="card">
            <div class="card-header"><h4 class="card-title mb-0">User Details</h4></div>
            <div class="card-body">
                @include('admin.users.partials.form', ['user' => null])
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/user-form.js') }}" defer></script>
@endpush
