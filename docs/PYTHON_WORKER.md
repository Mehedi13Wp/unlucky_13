# Python Worker

The Python worker communicates with Laravel through the authenticated worker API. It does not write directly to the application database.

## Commands

Run from the repository root.

```bash
python worker/run.py health
python worker/run.py ingest --campaign YOUR_CAMPAIGN_UUID --file worker/examples/leads.sample.json --dry-run
python worker/run.py ingest --campaign YOUR_CAMPAIGN_UUID --file worker/examples/leads.sample.json
python worker/run.py analysis-queue
python worker/run.py analyze --limit 5 --dry-run
python worker/run.py analyze --limit 5
python -m unittest discover -s worker/tests -v
```

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
```

Do not commit a real token.

## Next worker milestone

The next milestone is permitted discovery-source adapters so the worker can find candidate businesses, submit them through the discovery API, then pass them through this analyzer.
