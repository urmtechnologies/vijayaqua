<form action="{{ $user ? route('users.update', $user) : route('users.store') }}" method="POST" id="userForm">
    @csrf
    @if($user) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                   value="{{ old('name', $user?->name) }}" maxlength="255" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="mobile">Mobile Number <span class="text-danger">*</span></label>
            <input class="form-control @error('mobile') is-invalid @enderror" id="mobile" name="mobile"
                   type="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10"
                   value="{{ old('mobile', $user?->mobile) }}" required>
            @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="password">{{ $user ? 'New Password' : 'Password' }} @unless($user)<span class="text-danger">*</span>@endunless</label>
            <div class="va-password-field">
                <input class="form-control @error('password') is-invalid @enderror" id="password" name="password"
                       type="password" autocomplete="new-password" minlength="8" @unless($user) required @endunless>
                <button type="button" class="va-password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                    <svg class="va-eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="va-eye-closed d-none" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.1A11.7 11.7 0 0 1 12 6c6.4 0 10 6 10 6a15 15 0 0 1-3.2 3.7M6.1 6.9C3.5 8.7 2 12 2 12s3.6 6 10 6c1.6 0 3-.4 4.2-1M10 10a3 3 0 0 0 4 4"/></svg>
                </button>
            </div>
            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @if($user)<small class="text-muted">Leave blank to keep the current password.</small>@endif
        </div>

        <div class="col-md-6">
            <label class="form-label" for="password_confirmation">Confirm {{ $user ? 'New ' : '' }}Password @unless($user)<span class="text-danger">*</span>@endunless</label>
            <div class="va-password-field">
                <input class="form-control" id="password_confirmation" name="password_confirmation"
                       type="password" autocomplete="new-password" minlength="8" @unless($user) required @endunless>
                <button type="button" class="va-password-toggle" data-password-toggle="password_confirmation" aria-label="Show confirmation password" aria-pressed="false">
                    <svg class="va-eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="va-eye-closed d-none" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.1A11.7 11.7 0 0 1 12 6c6.4 0 10 6 10 6a15 15 0 0 1-3.2 3.7M6.1 6.9C3.5 8.7 2 12 2 12s3.6 6 10 6c1.6 0 3-.4 4.2-1M10 10a3 3 0 0 0 4 4"/></svg>
                </button>
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="salary">Monthly Salary (₹) <span class="text-danger">*</span></label>
            <input class="form-control @error('salary') is-invalid @enderror" id="salary" name="salary"
                   type="number" value="{{ old('salary', $user?->salary) }}" min="0" max="9999999999.99" step="0.01" required>
            @error('salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="role">Role <span class="text-danger">*</span></label>
            <input class="form-control @error('role') is-invalid @enderror" id="role" name="role"
                   list="roleSuggestions" value="{{ old('role', $user?->role ?? 'staff') }}"
                   maxlength="20" autocomplete="off" required>
            <datalist id="roleSuggestions">
                @foreach($roles as $roleOption)
                    <option value="{{ $roleOption }}"></option>
                @endforeach
            </datalist>
            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <small class="text-muted">Select an existing suggestion or type a new role. Use short names such as staff or supervisor.</small>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary" id="saveUser">{{ $user ? 'Save Changes' : 'Create User' }}</button>
        <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
    </div>
</form>
