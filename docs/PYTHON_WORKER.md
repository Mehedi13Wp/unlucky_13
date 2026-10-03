# Python Worker

The Python worker communicates with Laravel through the authenticated worker API. It does not write directly to the application database.

## Commands

Run from the repository root.

```bash
python worker/run.py health
python worker/run.py campaigns
python worker/run.py discover-osm --limit 10 --dry-run
python worker/run.py discover-osm --limit 10
python worker/run.py ingest --campaign YOUR_CAMPAIGN_UUID --file worker/examples/leads.sample.json --dry-run
python worker/run.py ingest --campaign YOUR_CAMPAIGN_UUID --file worker/examples/leads.sample.json
python worker/run.py analysis-queue
python worker/run.py analyze --limit 5 --dry-run
python worker/run.py analyze --limit 5
python worker/run.py contacts --limit 5 --dry-run
python worker/run.py contacts --limit 5
python worker/run.py drafts --limit 5 --dry-run
python worker/run.py drafts --limit 5
python worker/run.py send-approved --limit 5 --dry-run
python worker/run.py send-approved --limit 5
python -m unittest discover -s worker/tests -v
```

## Discovery

The first live adapter uses OpenStreetMap public business data through Nominatim and Overpass.

It processes only active campaigns, requires a supported industry plus target city/country, keeps businesses with published website URLs, preserves the source URL, and uses a minimum one-second discovery delay.

Initial category mappings include restaurants, cafes, salons/beauty, real estate agencies, recruitment/employment agencies, dentists, clinics, law firms, hotels, and gyms.

Public OpenStreetMap services are shared infrastructure. Use low limits for personal testing and configure a descriptive user agent.

Before ingesting a discovery batch, the worker checks candidate domains against the Laravel-side global discovery registry. A website already seen by any campaign is skipped before lead creation, so it is not analyzed or contacted again. The registry is persistent across worker restarts and records first/last seen timestamps.

## Automated website analyzer

The analyzer is intentionally conservative:

- inspects only the lead's supplied public business webpage
- performs one page request per lead; it is not a crawler
- follows redirects only when the destination is also a public HTTP/HTTPS address
- blocks localhost, private, link-local, reserved, and local-network destinations
- limits downloaded HTML size
- records factual signals such as HTTPS, page title, contact path, CTA visibility, viewport metadata, content density, target fit, and geography
- applies campaign qualification rules on the Laravel side
- never sends outreach
- leaves human review in control

If a website request fails, the lead remains pending/analyzing so it can be retried instead of being permanently rejected because of a temporary network problem.

## Environment

```env
UNLUCKY13_API_URL=http://127.0.0.1:8000
UNLUCKY13_WORKER_TOKEN=
UNLUCKY13_WORKER_TIMEOUT=15
UNLUCKY13_WORKER_RETRIES=3

UNLUCKY13_DISCOVERY_USER_AGENT="Unlucky13-LeadWorker/0.1"
UNLUCKY13_NOMINATIM_URL=https://nominatim.openstreetmap.org
UNLUCKY13_OVERPASS_URL=https://overpass-api.de/api/interpreter
UNLUCKY13_DISCOVERY_DELAY=1.1
```

Do not commit a real token.

## Public contact discovery

The contact worker processes only qualified leads from active campaigns. It inspects the public business website plus at most two same-domain contact-oriented pages. It records only public contact paths found on those pages:

- public email addresses
- public telephone links
- same-domain public contact page URLs

It does not use data brokers, guess email addresses, scrape private sources, or send outreach.

Preview without writing:

```bash
python worker/run.py contacts --limit 5 --dry-run
```

Store the discovered public contacts:

```bash
python worker/run.py contacts --limit 5
```

## Outreach drafts and review

The draft worker processes qualified leads that already have a public email address or public contact-page URL. Drafts are deterministic and fact-grounded for this milestone: they use the campaign service offer plus only supported lead facts and selected observed website signals.

The worker prefers a public email address. If no public email is available, it prepares copy for a public contact form. It never guesses a recipient and never sends a message.

Preview drafts without changing lead state:

```bash
python worker/run.py drafts --limit 5 --dry-run
```

Create drafts for human review:

```bash
python worker/run.py drafts --limit 5
```

Created drafts move the lead to `ready_for_review`. The lead detail page lets the authenticated owner approve or reject the latest pending draft. Approval changes the lead to `approved` but still sends nothing.

## Controlled email delivery

Only human-approved email drafts can enter automated delivery. The delivery API enforces:

- active campaign status
- lead and draft approval state
- campaign daily outreach limit
- active email suppression records
- one delivery record per draft
- no automatic HTTP retry on the send operation
- a real delivery-capable Laravel mailer; `log` and `array` are refused

Preview the queue and policy state without sending:

```bash
python worker/run.py send-approved --limit 5 --dry-run
```

After configuring a real mail provider in local environment variables, explicitly send eligible approved email drafts:

```bash
python worker/run.py send-approved --limit 5
```

Public contact-form drafts are intentionally not auto-submitted. They remain manual.

To suppress an email address immediately:

```bash
php artisan unlucky13:suppress-email person@example.com --reason=unsubscribe
```

## Next worker milestone

The next milestone is provider event handling for bounce, unsubscribe, reply classification, and interested-lead alerts.
