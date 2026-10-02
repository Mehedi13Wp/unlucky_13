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

Lead ingestion:

```http
POST /api/worker/campaigns/{campaign_uuid}/leads
```

Only active campaigns accept discovered leads. The API requires a valid website domain, normalizes the domain, deduplicates per campaign, and stores new records in the discovered state.

## Python worker client

From the repository root:

```bash
python worker/run.py health
```

Validate a candidate file without sending:

```bash
python worker/run.py ingest --campaign YOUR_CAMPAIGN_UUID --file worker/examples/leads.sample.json --dry-run
```

Send the candidates:

```bash
python worker/run.py ingest --campaign YOUR_CAMPAIGN_UUID --file worker/examples/leads.sample.json
```

The initial client deliberately has no scraping provider. It accepts structured JSON or JSONL candidates so connectivity, deduplication, retries, and lifecycle behavior can be tested before permitted discovery sources are attached.

## Reliability and safety

- API token is never committed
- client retries temporary connection failures, HTTP 429, and server errors
- batch command defaults to at most 25 candidates per run
- ingestion does not send email or outreach
- only active campaigns receive discoveries
