<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sign In | Vijay Aqua</title>

    <link rel="stylesheet" href="{{ asset('assets/libs/simplebar/simplebar.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.min.css') }}">
    <link rel="shortcut icon" href="{{ asset('assets/images/logo-sm.png') }}">

    <style>
        .va-password-field {
            position: relative;
        }

        .va-password-field .form-control {
            padding-right: 48px;
        }

        .va-password-toggle {
            position: absolute;
            top: 50%;
            right: 7px;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            padding: 0;
            transform: translateY(-50%);
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #78889b;
            cursor: pointer;
        }

        .va-password-toggle:hover,
        .va-password-toggle:focus-visible {
            color: #9d6cc9;
            background: rgba(157, 108, 201, .09);
        }

        .va-password-toggle:focus-visible {
            outline: 2px solid #9d6cc9;
            outline-offset: 1px;
        }

        .va-password-toggle svg {
            width: 19px;
            height: 19px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
    </style>
</head>

<body>
    <div class="position-relative min-vh-100">
        <div class="row gx-0">

            <div class="col-xl-5">
                <div
                    class="row justify-content-center align-items-center p-10 min-vh-100 bg-body-secondary position-relative">
                    <div class="col-md-7 col-lg-6 col-xl-8 col-xxl-7">

                        <a href="{{ route('login') }}" class="text-nowrap d-block w-100 text-decoration-none">
                            <span class="text-primary fs-3 fw-bold">
                                Vijay Aqua
                            </span>
                        </a>

                        <h3 class="mb-3 mt-8">Sign In</h3>

                        <p class="text-muted mb-8">
                            Access your Vijay Aqua dashboard.
                        </p>

                        <div id="loginAlert" class="alert alert-danger d-none" role="alert"></div>

                        <form id="loginForm" action="{{ route('login.submit') }}" method="POST">
                            @csrf

                            <div class="mb-5">
                                <label for="mobile" class="form-label">
                                    Mobile Number
                                </label>

                                <input type="tel" class="form-control" id="mobile" name="mobile"
                                    placeholder="Enter 10-digit mobile number" inputmode="numeric" pattern="[0-9]{10}"
                                    maxlength="10" autocomplete="username" required>

                                <div id="mobileError" class="invalid-feedback"></div>
                            </div>

                            <div class="mb-5">
                                <label for="password" class="form-label">
                                    Password
                                </label>

                                <div class="va-password-field">
                                    <input type="password" class="form-control" id="password" name="password"
                                        placeholder="Enter password" autocomplete="current-password" required>

                                    <button type="button" class="va-password-toggle" id="togglePassword"
                                        aria-label="Show password" aria-pressed="false" title="Show password">

                                        {{-- Eye: password hidden --}}
                                        <svg id="eyeOpen" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>

                                        {{-- Eye with slash: password visible --}}
                                        <svg id="eyeClosed" class="d-none" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M3 3l18 18" />
                                            <path
                                                d="M10.6 6.1A11.7 11.7 0 0 1 12 6c6.4 0 10 6 10 6a15 15 0 0 1-3.2 3.7" />
                                            <path d="M6.1 6.9C3.5 8.7 2 12 2 12s3.6 6 10 6c1.6 0 3-.4 4.2-1" />
                                            <path d="M10 10a3 3 0 0 0 4 4" />
                                        </svg>
                                    </button>
                                </div>

                                <div id="passwordError" class="text-danger small mt-1 d-none"></div>
                            </div>

                            <button type="submit" id="loginButton" class="btn btn-primary w-100">
                                <span id="loginSpinner" class="spinner-border spinner-border-sm me-2 d-none"
                                    role="status" aria-hidden="true"></span>
                                <span id="loginButtonText">Sign In</span>
                            </button>

                            <div class="text-muted pt-14">
                                <p>© {{ date('Y') }} Vijay Aqua.</p>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

            <div class="col-xl-7 d-none d-md-block">
                <div
                    class="h-100 d-flex align-items-center overflow-hidden justify-content-center position-relative z-2 hero-section bg-body">

                    <div class="floating-card position-absolute card-1">
                        <div class="d-flex gap-5">
                            <div class="bg-body-secondary shadow-lg rounded-3 px-4 py-2 team-info">
                                <h6 class="mb-0">Vijay Aqua</h6>
                                <small class="text-muted mb-0">
                                    Water Management
                                </small>
                            </div>

                            <div class="avatar avatar-md bg-body-secondary shadow-lg rounded-3 linkedin">
                                <img src="{{ asset('assets/images/users/avatar-2.png') }}" alt=""
                                    class="avatar-xs">
                            </div>
                        </div>
                    </div>

                    <div class="floating-card position-absolute card-2 d-none d-xxl-block">
                        <div class="d-flex gap-5">
                            <div class="bg-body-secondary shadow-lg rounded-3 px-4 py-2 team-info">
                                <h6 class="mb-0">Operations</h6>
                                <small class="text-muted mb-0">
                                    Daily Overview
                                </small>
                            </div>

                            <div class="avatar avatar-md bg-body-secondary shadow-lg rounded-3 linkedin">
                                <img src="{{ asset('assets/images/users/avatar-1.png') }}" alt=""
                                    class="avatar-xs">
                            </div>
                        </div>
                    </div>

                    <div class="floating-card position-absolute card-3">
                        <div class="d-flex gap-5">
                            <div class="avatar avatar-md bg-body-secondary shadow-lg rounded-3 linkedin">
                                <img src="{{ asset('assets/images/users/avatar-7.png') }}" alt=""
                                    class="avatar-xs">
                            </div>

                            <div class="bg-body-secondary shadow-lg rounded-3 px-4 py-2 team-info">
                                <h6 class="mb-0">Customers</h6>
                                <small class="text-muted mb-0">
                                    Manage Records
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="floating-card position-absolute card-4 d-none d-xxl-block">
                        <div class="d-flex gap-5">
                            <div class="avatar avatar-md bg-body-secondary shadow-lg rounded-3 linkedin">
                                <img src="{{ asset('assets/images/users/avatar-8.png') }}" alt=""
                                    class="avatar-xs">
                            </div>

                            <div class="bg-body-secondary shadow-lg rounded-3 px-4 py-2 team-info">
                                <h6 class="mb-0">Orders</h6>
                                <small class="text-muted mb-0">
                                    Track Activity
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="floating-card position-absolute card-5">
                        <div class="d-flex gap-5">
                            <div class="avatar avatar-md bg-body-secondary shadow-lg rounded-3 linkedin">
                                <img src="{{ asset('assets/images/users/avatar-9.png') }}" alt=""
                                    class="avatar-xs">
                            </div>

                            <div class="bg-body-secondary shadow-lg rounded-3 px-4 py-2 team-info">
                                <h6 class="mb-0">Reports</h6>
                                <small class="text-muted mb-0">
                                    Stay Informed
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="floating-card position-absolute card-6">
                        <div class="w-72 shadow-lg rounded-3 overflow-hidden">
                            <img src="{{ asset('assets/images/auth/img-1.png') }}" alt=""
                                class="img-fluid h-100 w-100 object-fit-cover">
                        </div>
                    </div>

                    <div class="floating-card position-absolute card-7">
                        <div class="w-80 shadow-lg rounded-3 overflow-hidden">
                            <img src="{{ asset('assets/images/auth/img-4.png') }}" alt=""
                                class="img-fluid w-100 h-100 object-fit-cover">
                        </div>
                    </div>

                    <div class="floating-card position-absolute card-8">
                        <div class="h-40 shadow-lg rounded-3 overflow-hidden">
                            <img src="{{ asset('assets/images/auth/img-3.png') }}" alt=""
                                class="img-fluid w-100 h-100 object-fit-cover">
                        </div>
                    </div>

                    <div class="text-center z-index-2 position-relative">
                        <p class="display-5 text-body fw-normal mb-6">
                            Welcome to Vijay Aqua
                            <br>
                            <span class="text-primary display-6 fw-normal">
                                Management Dashboard
                            </span>
                        </p>

                        <p class="px-4 fs-14 max-w-75 mx-auto">
                            Manage your daily work from one place.
                        </p>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <script src="{{ asset('assets/libs/jquery/jquery.min.js') }}"></script>

    <script>
        $(function() {
            const form = $('#loginForm');
            const button = $('#loginButton');
            const spinner = $('#loginSpinner');
            const buttonText = $('#loginButtonText');
            const alertBox = $('#loginAlert');

            let redirecting = false;

            $('#togglePassword').on('click', function() {
                const passwordInput = $('#password');
                const showPassword = passwordInput.attr('type') === 'password';

                passwordInput.attr(
                    'type',
                    showPassword ? 'text' : 'password'
                );

                $('#eyeOpen').toggleClass('d-none', showPassword);
                $('#eyeClosed').toggleClass('d-none', !showPassword);

                $(this)
                    .attr(
                        'aria-label',
                        showPassword ? 'Hide password' : 'Show password'
                    )
                    .attr(
                        'title',
                        showPassword ? 'Hide password' : 'Show password'
                    )
                    .attr('aria-pressed', String(showPassword));
            });

            form.on('submit', function(event) {
                event.preventDefault();

                if (button.prop('disabled')) {
                    return;
                }

                alertBox.addClass('d-none').text('');

                $('#mobile, #password').removeClass('is-invalid');
                $('#mobileError').text('');
                $('#passwordError').addClass('d-none').text('');

                if (!this.reportValidity()) {
                    return;
                }

                button.prop('disabled', true);
                spinner.removeClass('d-none');
                buttonText.text('Signing in...');

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    dataType: 'json',
                    headers: {
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        if (response.redirect) {
                            redirecting = true;
                            window.location.assign(response.redirect);
                        }
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON || {};

                        if (response.errors) {
                            $.each(response.errors, function(field, messages) {
                                if (field === 'mobile') {
                                    $('#mobile').addClass('is-invalid');
                                    $('#mobileError').text(messages[0]);
                                }

                                if (field === 'password') {
                                    $('#password').addClass('is-invalid');
                                    $('#passwordError')
                                        .text(messages[0])
                                        .removeClass('d-none');
                                }
                            });
                        }

                        const message = xhr.status === 419 ?
                            'Session expired. Refresh the page and try again.' :
                            (response.message ||
                                'Unable to sign in. Please try again.');

                        alertBox.text(message).removeClass('d-none');
                    },
                    complete: function() {
                        if (!redirecting) {
                            button.prop('disabled', false);
                            spinner.addClass('d-none');
                            buttonText.text('Sign In');
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>
