@extends('layouts.admin')
@section('title', 'Change Password')
@section('page-title', 'Change Password')
@section('content')
<div class="row"><div class="col-lg-6"><div class="card"><div class="card-body">
    <p class="text-muted">You can change your password here. Profile details are managed by an administrator.</p>
    <form method="POST" action="{{ route('password.update') }}" data-safe-submit>@csrf @method('PUT')
        @foreach(['current_password' => 'Current Password', 'password' => 'New Password', 'password_confirmation' => 'Confirm New Password'] as $field => $label)
            <div class="mb-3"><label for="{{ $field }}" class="form-label">{{ $label }}</label><div class="va-password-field"><input id="{{ $field }}" name="{{ $field }}" class="form-control" type="password" required autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" @if($field !== 'current_password') minlength="8" @endif>
                <button type="button" class="va-password-toggle" data-password-toggle="{{ $field }}" aria-label="Show {{ $label }}" aria-pressed="false"><svg class="va-eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg class="va-eye-closed d-none" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.1A11.7 11.7 0 0 1 12 6c6.4 0 10 6 10 6a15 15 0 0 1-3.2 3.7M6.1 6.9C3.5 8.7 2 12 2 12s3.6 6 10 6c1.6 0 3-.4 4.2-1M10 10a3 3 0 0 0 4 4"/></svg></button></div>
                @error($field)<small class="text-danger">{{ $message }}</small>@enderror
            </div>
        @endforeach
        <button class="btn btn-primary" data-submit-button><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span> <span data-submit-label>Change Password</span></button>
    </form>
</div></div></div></div>
@endsection
@push('scripts')<script src="{{ asset('assets/js/record-form.js') }}" defer></script><script src="{{ asset('assets/js/user-form.js') }}" defer></script>@endpush
