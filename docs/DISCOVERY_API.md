# Discovery Worker API

The private discovery API is the boundary between the Python worker and Laravel. The worker never writes directly to Laravel database tables.

## Authentication

Set a long random token in the project root `.env` file:

```env
UNLUCKY13_WORKER_TOKEN=replace-with-a-long-random-secret
UNLUCKY13_API_URL=http://127.0.0.1:8000
```

Generate a token locally:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

After editing `.env`:

```bash
php artisan config:clear
```

## API endpoints

Health:

```http
GET /api/worker/health
```

Active campaign feed:

```http
GET /api/worker/campaigns
```

Only campaigns in the `active` state are returned. This allows the Python worker to discover work without direct database access.

Lead ingestion:

```http
POST /api/worker/campaigns/{campaign_uuid}/leads
```

Only active campaigns accept discovered leads. The API requires a valid website domain, normalizes the domain, deduplicates per campaign, and stores new records in the discovered state.

## Python worker client

From the repository root:

```bash
python worker/run.py health
python worker/run.py campaigns
```

Validate a candidate file without sending:

```bash
python worker/run.py ingest --campaign YOUR_CAMPAIGN_UUID --file worker/examples/leads.sample.json --dry-run
```

Send the candidates:

```bash
python worker/run.py ingest --campaign YOUR_CAMPAIGN_UUID --file worker/examples/leads.sample.json
```

## OpenStreetMap discovery adapter

The first live discovery adapter uses public OpenStreetMap business data. It intentionally keeps the scope conservative:

- only active campaigns are processed
- a campaign must have a supported industry mapping and target geography
- the adapter keeps only businesses that publish a website URL
- Nominatim geocoding is rate-limited to at least one second between requests
- discovery records keep their OpenStreetMap source URL for auditability
- no email is sent and no outreach occurs during discovery

Supported initial categories include restaurants, cafes, salons/beauty, real estate agencies, recruitment/employment agencies, dentists, clinics, law firms, hotels, and gyms.

Preview discoveries without writing leads:

```bash
python worker/run.py discover-osm --limit 10 --dry-run
```

Ingest discovered candidates for all active campaigns:

```bash
python worker/run.py discover-osm --limit 10
```

Process only one active campaign:

```bash
python worker/run.py discover-osm --campaign YOUR_CAMPAIGN_UUID --limit 10
```

After discovery, run the website analyzer:

```bash
python worker/run.py analyze --limit 10
```

Public OpenStreetMap/Nominatim/Overpass services have usage policies and shared capacity. This adapter is for conservative personal testing. Keep limits low, identify the application with a custom user agent, and move to an appropriate hosted/self-hosted provider if usage grows.

## Reliability and safety

- API token is never committed
- client retries temporary connection failures, HTTP 429, and server errors
- discovery commands use conservative batch limits
- ingestion does not send email or outreach
- only active campaigns receive discoveries
- source attribution is preserved
