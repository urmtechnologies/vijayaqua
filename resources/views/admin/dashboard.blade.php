@extends('layouts.admin')

@section('page-title', 'Dashboard')

@section('content')
@if(!$canSeeUsers)<div class="card"><div class="card-body"><h4>Welcome, {{ auth()->user()->name }}</h4><p class="text-muted mb-0">Choose a module from the sidebar to get started.</p></div></div>@else
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card card-h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div><p class="text-muted mb-2">Users</p><h2 class="mb-0">{{ number_format($userCount) }}</h2></div>
                <span class="va-stat-icon"><i class="mdi mdi-account-group-outline"></i></span>
            </div>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card card-h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div><p class="text-muted mb-2">Configured monthly salaries</p><h2 class="mb-0">₹{{ number_format($monthlySalary, 2) }}</h2></div>
                <span class="va-stat-icon"><i class="mdi mdi-wallet-outline"></i></span>
            </div>
        </div></div>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">Recently added users</h4>
        @if(\App\Support\Access::allowed('users', 'create'))<a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">Add User</a>@endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Mobile</th><th>Role</th><th>Salary</th><th>Added</th></tr></thead>
            <tbody>
            @forelse($recentUsers as $user)
                <tr><td>{{ $user->name }}</td><td>{{ $user->mobile }}</td><td>{{ ucfirst($user->role) }}</td><td>₹{{ number_format($user->salary, 2) }}</td><td>{{ $user->created_at->format('d M Y') }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-5">No users yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endif
@endsection
