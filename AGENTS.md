# Agent guidance

## Project map

- `wp-content/themes/atelier-shop/`: storefront theme.
- `wp-content/mu-plugins/`: contact, newsletter, and store behavior.
- `Dockerfile`, `railway/`: local/deployment image and startup.
- `tests/`: disposable recovery, storefront, accessibility-related, and email checks; see `tests/README.md`.

## Safe work

- Use fictional data and local disposable services. The recovery integration test replaces its donor container and database, then removes only its own temporary containers and files.
- Do not read `.env`, production credentials, or customer records. Do not contact production services or run the public-store test unless explicitly requested.
- Never deploy, change hosting settings, migrate or delete live data, send email, or initiate payments without the user's explicit approval.
- Before handing off, run the Docker image build and `ATELIER_TEST_IMAGE=<local-image> python3 tests/recovery-integration.py` where Docker is available. Other checks are documented in `tests/README.md`.
- Keep changes reviewable; do not commit or push without explicit authorization.
