<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#070a0f">
    <title>@yield('title', 'Dashboard') · Unlucky 13</title>
    <link rel="stylesheet" href="{{ asset('css/unlucky13.css') }}">
    @stack('styles')
</head>
<body class="app-body">
<div class="app-shell" id="app-shell">
    <div class="sidebar-backdrop" data-sidebar-close aria-hidden="true"></div>

    <aside class="sidebar" id="app-sidebar" aria-label="Primary navigation">
        <div class="sidebar-head">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="Unlucky 13 dashboard">
                <span class="brand-mark">13</span>
                <span class="brand-copy">
                    <strong>Unlucky 13</strong>
                    <small>AI Lead Worker</small>
                </span>
            </a>

            <button class="icon-button sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>

        <nav class="nav">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <span class="nav-link-main">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z"/></svg>
                    </span>
                    <span>Dashboard</span>
                </span>
            </a>

            <div class="nav-section">Workspace</div>

            <a class="nav-link {{ request()->routeIs('campaigns.*') ? 'active' : '' }}" href="{{ route('campaigns.index') }}">
                <span class="nav-link-main">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M5 7h14M5 12h14M5 17h8"/><circle cx="18" cy="17" r="2"/></svg>
                    </span>
                    <span>Campaigns</span>
                </span>
            </a>

            <a class="nav-link {{ request()->routeIs('leads.*') ? 'active' : '' }}" href="{{ route('leads.index') }}">
                <span class="nav-link-main">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <span>Leads</span>
                </span>
            </a>

            <a class="nav-link {{ request()->routeIs('review-queue.*') ? 'active' : '' }}" href="{{ route('review-queue.index') }}">
                <span class="nav-link-main">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1 2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    </span>
                    <span>Review Queue</span>
                </span>
            </a>

            <div class="nav-section">Automation</div>

            <a class="nav-link {{ request()->routeIs('worker.*') ? 'active' : '' }}" href="{{ route('worker.index') }}">
                <span class="nav-link-main">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M12 7V3M8 7h8"/><circle cx="8" cy="16" r="1"/><circle cx="16" cy="16" r="1"/></svg>
                    </span>
                    <span>Worker</span>
                </span>
            </a>

            <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                <span class="nav-link-main">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg>
                    </span>
                    <span>Reports</span>
                </span>
            </a>
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
                <button class="button button-secondary button-full" type="submit">
                    <span>Sign out</span>
                </button>
            </form>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-primary">
                <button class="icon-button menu-button" type="button" data-sidebar-open aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>

                <div class="page-heading">
                    <p class="eyebrow">Private workspace</p>
                    <h1>@yield('heading', 'Dashboard')</h1>
                </div>
            </div>

            <div class="topbar-actions">
                @yield('topbar-actions')
                <div class="status-pill" title="Local application is responding">
                    <span class="status-dot"></span>
                    <span>System online</span>
                </div>
            </div>
        </header>

        <div class="content-wrap">
            <section class="content">
                @if (session('success'))
                    <div class="flash-success" role="status">
                        <span class="flash-icon">✓</span>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif
                @if (session('error'))
                    <div class="flash-success" role="alert">
                        <span class="flash-icon">!</span>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @yield('content')
            </section>
        </div>
    </main>
</div>

<script src="{{ asset('js/unlucky13.js') }}" defer></script>
@stack('scripts')
</body>
</html>
