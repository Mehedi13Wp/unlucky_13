# Unlucky 13

Private AI-powered lead generation and outreach worker.

## Goal
Build a professional local-first system that discovers relevant businesses, qualifies genuine opportunities, prepares personalized outreach for review, tracks outcomes, and alerts on interested replies.

## Architecture
- Laravel: dashboard, campaigns, leads, outreach review, reporting
- Python: discovery and analysis worker
- Database: campaigns, leads, contacts, messages, suppression/history
- Integrations: compliant data sources, email provider, AI provider, Telegram alerts

## Development workflow
GitHub is the source of truth. Pull before starting work and commit/push completed local changes. Secrets belong in local environment files and must never be committed.

## Safety defaults
- Review Before Send by default
- conservative sending/rate limits
- deduplication and suppression lists
- unsubscribe, bounce, and reply handling
- personalization based only on observed facts
- public/permitted business data sources

## Status
Project initialized. Next milestone: Laravel application bootstrap and campaign/lead data model.
