# Python Worker

The first Python milestone is an API client, not an autonomous scraper. It validates structured candidates and sends them to Laravel safely.

## Commands

Run from the repository root.

```bash
python worker/run.py health
python worker/run.py ingest --campaign YOUR_CAMPAIGN_UUID --file worker/examples/leads.sample.json --dry-run
python worker/run.py ingest --campaign YOUR_CAMPAIGN_UUID --file worker/examples/leads.sample.json
python -m unittest discover -s worker/tests -v
```

## Environment

```env
UNLUCKY13_API_URL=http://127.0.0.1:8000
UNLUCKY13_WORKER_TOKEN=
UNLUCKY13_WORKER_TIMEOUT=15
UNLUCKY13_WORKER_RETRIES=3
```

Do not commit a real token.

## Next worker milestone

The next step is the analysis and qualification pipeline. It will inspect a discovered business using explicit permitted inputs, produce auditable observed signals, calculate qualification output, and update the Laravel lead lifecycle through the authenticated API.
