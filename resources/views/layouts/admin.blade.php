<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | Vijay Aqua</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/vijay-aqua.css') }}">
    @stack('styles')
</head>
<body data-layout="vertical" data-sidebar="large">
<div id="layout-wrapper">
    <header id="page-topbar">
        <div class="navbar-header">
            <div class="navbar-logo-box">
                <a class="logo va-brand" href="{{ route('dashboard') }}">VA <span>Vijay Aqua</span></a>
                <button type="button" class="btn btn-icon top-icon sidebar-btn" id="sidebar-btn" aria-label="Toggle sidebar">
                    <i class="mdi mdi-menu fs-20"></i>
                </button>
            </div>
            <div class="d-flex align-items-center gap-3 ms-auto px-3">
                <span class="d-none d-sm-inline text-muted">{{ auth()->user()->name }}</span>
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm">Logout</button>
                </form>
            </div>
        </div>
    </header>

    @include('layouts.partials.sidebar')
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h4 class="mb-0">@yield('page-title', 'Dashboard')</h4>
                    @yield('page-action')
                </div>
                @if(session('success'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if($errors->has('stock_entry') || $errors->has('sale'))
                    <div class="alert alert-danger" role="alert">{{ $errors->first('stock_entry') ?: $errors->first('sale') }}</div>
                @endif
                @yield('content')
            </div>
        </div>
        <footer class="footer"><div class="container-fluid">© {{ date('Y') }} Vijay Aqua</div></footer>
    </div>
</div>
@include('shared.confirm-delete')

<script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/vijay-aqua.js') }}" defer></script>
@stack('scripts')
</body>
</html>
