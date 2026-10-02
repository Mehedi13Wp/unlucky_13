<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#070a0f">
    <title>Sign in · Unlucky 13</title>
    <link rel="stylesheet" href="{{ asset('css/unlucky13.css') }}">
</head>
<body class="auth-body">
<main class="auth-shell">
    <section class="auth-showcase" aria-hidden="true">
        <div class="auth-showcase-inner">
            <div class="auth-showcase-brand">
                <span class="brand-mark brand-mark-large">13</span>
                <span>
                    <strong>Unlucky 13</strong>
                    <small>Private AI Lead Worker</small>
                </span>
            </div>

            <div class="auth-message">
                <p class="eyebrow">Local-first sales workspace</p>
                <h1>From research to qualified conversations.</h1>
                <p>Build focused campaigns, review real business signals, and keep every outreach decision under your control.</p>
            </div>

            <div class="auth-feature-grid">
                <div class="auth-feature">
                    <span class="auth-feature-number">01</span>
                    <strong>Target precisely</strong>
                    <small>Campaign rules define who the worker should research.</small>
                </div>
                <div class="auth-feature">
                    <span class="auth-feature-number">02</span>
                    <strong>Qualify first</strong>
                    <small>Observed signals matter before any outreach is prepared.</small>
                </div>
                <div class="auth-feature">
                    <span class="auth-feature-number">03</span>
                    <strong>Review before send</strong>
                    <small>Human approval stays central to the workflow.</small>
                </div>
            </div>
        </div>
    </section>

    <section class="auth-form-side">
        <div class="auth-panel">
            <div class="auth-mobile-brand">
                <span class="brand-mark">13</span>
                <span>
                    <strong>Unlucky 13</strong>
                    <small>AI Lead Worker</small>
                </span>
            </div>

            <div class="auth-heading">
                <p class="eyebrow">Secure access</p>
                <h2>Welcome back</h2>
                <p>Sign in to your private workspace.</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                @csrf

                <label class="field">
                    <span>Email address</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        inputmode="email"
                        autofocus
                        required
                        placeholder="you@example.com"
                    >
                </label>

                <label class="field">
                    <span>Password</span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        placeholder="Enter your password"
                    >
                </label>

                @if ($errors->any())
                    <div class="form-error" role="alert">{{ $errors->first() }}</div>
                @endif

                <label class="check-row">
                    <input type="checkbox" name="remember" value="1">
                    <span>Keep me signed in on this device</span>
                </label>

                <button class="button button-primary button-full button-lg" type="submit">Sign in</button>
            </form>

            <div class="auth-security-note">
                <span class="security-dot"></span>
                <p>Private workspace. Public registration is disabled.</p>
            </div>
        </div>
    </section>
</main>
</body>
</html>
