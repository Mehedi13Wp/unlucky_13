<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Unlucky 13</title>
    <link rel="stylesheet" href="{{ asset('css/unlucky13.css') }}">
</head>
<body class="app-body">
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark">13</span>
            <span>
                <strong>Unlucky 13</strong>
                <small>AI Lead Worker</small>
            </span>
        </a>

        <nav class="nav">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <span>Dashboard</span>
            </a>

            <div class="nav-section">Workspace</div>
            <a class="nav-link {{ request()->routeIs('campaigns.*') ? 'active' : '' }}" href="{{ route('campaigns.index') }}">
                <span>Campaigns</span>
            </a>
            <span class="nav-link disabled"><span>Leads</span><small>Next</small></span>
            <span class="nav-link disabled"><span>Review Queue</span><small>Soon</small></span>

            <div class="nav-section">Automation</div>
            <span class="nav-link disabled"><span>Worker</span><small>Soon</small></span>
            <span class="nav-link disabled"><span>Reports</span><small>Soon</small></span>
        </nav>

        <div class="sidebar-footer">
            <div class="user-card">
                <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="user-meta">
                    <strong>{{ auth()->user()->name }}</strong>
                    <small>{{ auth()->user()->email }}</small>
                </span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button button-secondary button-full" type="submit">Sign out</button>
            </form>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">Private workspace</p>
                <h1>@yield('heading', 'Dashboard')</h1>
            </div>
            <div class="topbar-actions">
                @yield('topbar-actions')
                <div class="status-pill"><span></span> Local system online</div>
            </div>
        </header>

        <section class="content">
            @if (session('success'))
                <div class="flash-success">{{ session('success') }}</div>
            @endif

            @yield('content')
        </section>
    </main>
</div>
</body>
</html>
