@extends('layouts.app')

@section('title', 'Settings')
@section('heading', 'Settings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sender-accounts.css') }}">
@endpush

@section('content')
    <section class="sender-hero">
        <div>
            <p class="eyebrow">Workspace settings</p>
            <h2>Keep account, delivery and automation controls in one place.</h2>
            <p>Use this page as the control center for your Unlucky 13 workspace. Sensitive sender credentials remain managed inside Sender Accounts.</p>
        </div>

        <div class="sender-stats">
            <div>
                <span>Account</span>
                <strong style="font-size: 1rem;">Active</strong>
            </div>
            <div>
                <span>Workspace</span>
                <strong style="font-size: 1rem;">Private</strong>
            </div>
        </div>
    </section>

    <section class="sender-grid">
        <article class="sender-panel">
            <header>
                <div>
                    <p class="eyebrow">Account</p>
                    <h3>Profile</h3>
                </div>
            </header>

            <div class="sender-import">
                <p><strong>{{ auth()->user()->name }}</strong><br>{{ auth()->user()->email }}</p>
                <p>Your signed-in identity is used to isolate campaigns, sender accounts and automation settings from other users.</p>
            </div>
        </article>

        <article class="sender-panel">
            <header>
                <div>
                    <p class="eyebrow">Email delivery</p>
                    <h3>Sender Accounts</h3>
                </div>
            </header>

            <div class="sender-import">
                <p>Add SMTP accounts, manage daily limits, import sender CSV files and control approved-queue delivery sessions.</p>
                <a class="button button-primary" href="{{ route('sender-accounts.index') }}">Manage sender accounts</a>
            </div>
        </article>

        <article class="sender-panel">
            <header>
                <div>
                    <p class="eyebrow">Automation</p>
                    <h3>Worker</h3>
                </div>
            </header>

            <div class="sender-import">
                <p>Open the worker workspace to review runtime state and control the lead-processing worker.</p>
                <a class="button button-secondary" href="{{ route('worker.index') }}">Open worker</a>
            </div>
        </article>

        <article class="sender-panel">
            <header>
                <div>
                    <p class="eyebrow">Insights</p>
                    <h3>Reports</h3>
                </div>
            </header>

            <div class="sender-import">
                <p>Review outreach activity and results without mixing reporting controls into the automation screens.</p>
                <a class="button button-secondary" href="{{ route('reports.index') }}">View reports</a>
            </div>
        </article>
    </section>
@endsection
