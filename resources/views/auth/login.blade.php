<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · Unlucky 13</title>
    <link rel="stylesheet" href="{{ asset('css/unlucky13.css') }}">
</head>
<body class="auth-body">
<main class="auth-wrap">
    <section class="auth-panel">
        <div class="auth-brand">
            <span class="brand-mark brand-mark-large">13</span>
            <div>
                <p class="eyebrow">Private AI workspace</p>
                <h1>Unlucky 13</h1>
            </div>
        </div>

        <p class="auth-copy">Sign in to manage campaigns, qualify leads, review outreach, and monitor your worker.</p>

        <form method="POST" action="{{ route('login.store') }}" class="auth-form">
            @csrf

            <label class="field">
                <span>Email</span>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
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
                    placeholder="••••••••••••"
                >
            </label>

            @if ($errors->any())
                <div class="form-error">{{ $errors->first() }}</div>
            @endif

            <label class="check-row">
                <input type="checkbox" name="remember" value="1">
                <span>Keep me signed in on this device</span>
            </label>

            <button class="button button-primary button-full" type="submit">Sign in</button>
        </form>

        <p class="auth-note">No public registration. Accounts are created locally from the command line.</p>
    </section>
</main>
</body>
</html>
