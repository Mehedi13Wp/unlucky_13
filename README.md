# Unlucky 13

Private AI-powered lead generation and outreach worker.

## Goal

Build a professional local-first system that discovers relevant businesses, qualifies genuine opportunities, prepares personalized outreach for review, tracks outcomes, and alerts on interested replies.

## Architecture

- Laravel: authentication, dashboard, campaigns, leads, outreach review, reporting, APIs
- Python: discovery and analysis worker
- Database: campaigns, leads, contacts, outreach drafts, messages, suppression/history
- Integrations: compliant data sources, email provider, AI provider, Telegram alerts

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for application boundaries and lifecycle details.
See [docs/DISCOVERY_API.md](docs/DISCOVERY_API.md) and [docs/PYTHON_WORKER.md](docs/PYTHON_WORKER.md) for worker setup and API usage.

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
- private authentication and responsive application shell complete
- campaign management complete
- premium responsive dashboard complete
- professional responsive campaign workspace complete
- lead management workspace foundation complete
- authenticated discovery API foundation complete
- Python discovery worker client complete
- analysis and qualification API workflow complete
- automated website signal analyzer complete
- active-campaign worker feed complete
- first live permitted discovery adapter complete (OpenStreetMap/Nominatim/Overpass)
- qualified-lead public contact discovery complete
- fact-grounded personalized outreach drafting complete
- human Review Before Send approve/reject workflow complete
- controlled approved-email delivery foundation complete with daily-limit and suppression checks
- persistent global website deduplication complete across campaigns
- persistent multi-campaign worker scheduler complete with pause/resume awareness and crash-safe retries
- dashboard Start/Stop control for the local Windows automation worker complete
- optional Windows logon auto-start scripts remain available but are not required
- next product milestone: provider events, unsubscribe/bounce/reply handling, and alerts
