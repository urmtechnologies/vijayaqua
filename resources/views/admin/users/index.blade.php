@extends('layouts.admin')

@section('title', 'Users')
@section('page-title', 'Users')
@section('page-action')
    <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="mdi mdi-plus me-1"></i> New User</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h4 class="card-title mb-0">Users</h4>
            <button type="button" class="btn btn-outline-primary d-md-none" data-bs-toggle="offcanvas"
                data-bs-target="#mobileFilters" aria-controls="mobileFilters">
                <i class="mdi mdi-filter-variant me-1"></i> Filters
            </button>
        </div>
        <div class="card-body">
            <form id="desktopFilters" class="row g-2 align-items-end mb-4 d-none d-md-flex"
                action="{{ route('users.index') }}" method="GET">
                @include('admin.users.partials.filters')
            </form>
            <div id="userResults" class="va-results" aria-live="polite">
                @include('admin.users.partials.results')
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserTitle">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Delete <strong id="deleteUserName"></strong>? The user will be removed from the active list.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteUserForm" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" id="confirmDeleteUser">Delete User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="mobileFilters" aria-labelledby="mobileFiltersLabel">
        <div class="offcanvas-header">
            <h5 id="mobileFiltersLabel" class="offcanvas-title">Filter Users</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form id="mobileFilterForm" action="{{ route('users.index') }}" method="GET" class="row g-3">
                @include('admin.users.partials.filters')
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/users-list.js') }}" defer></script>
@endpush
