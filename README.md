# Unlucky 13

Private AI-powered lead generation and outreach worker.

## Goal

Build a professional local-first system that discovers relevant businesses, qualifies genuine opportunities, prepares personalized outreach for review, tracks outcomes, and alerts on interested replies.

## Architecture

- Laravel: authentication, dashboard, campaigns, leads, outreach review, reporting, APIs
- Python: discovery and analysis worker
- Database: campaigns, leads, contacts, messages, suppression/history
- Integrations: compliant data sources, email provider, AI provider, Telegram alerts

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for application boundaries and lifecycle details.

## Local development

After cloning and configuring the environment:

```bash
composer install
php artisan migrate
php artisan unlucky13:create-admin
php artisan serve
```

Open `http://127.0.0.1:8000` and sign in with the account created by the command.

## Development workflow

GitHub is the source of truth. Pull before starting work and commit/push completed local changes. Secrets belong in local environment files and must never be committed.

## Safety defaults

- Review Before Send is enabled by default
- automatic sending is disabled until the delivery/suppression pipeline is implemented
- conservative campaign-level sending limits
- deduplication and suppression lists
- unsubscribe, bounce, and reply handling
- personalization based only on observed facts
- public/permitted business data sources

## Status

- Laravel 13 application bootstrapped
- campaign and lead domain foundation complete
- private authentication and dashboard shell complete
- campaign management complete: targeting, qualification rules, limits, activation/pause/archive
- next milestone: lead management and discovery API
