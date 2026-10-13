# Local verification

These checks use fictional data and disposable containers. They never need
production credentials or contact GitHub: the integration test redirects its
synthetic SSH repository URL to a temporary local bare repository.

## Recovery

Build the actual deployment image, then run the end-to-end test:

```sh
docker build -t atelier-audit-improved:latest .
ATELIER_TEST_IMAGE=atelier-audit-improved:latest python3 tests/recovery-integration.py
```

Requires Docker and Python 3; ports 18087/18088 must be free. The test replaces
the entire donor container/database and removes both containers and temporary
recovery data afterward. It verifies a native WooCommerce order, private inbox
message/subscriber, newly uploaded attachment and generated thumbnails, and
refusal of missing/corrupt media before SQL import.

Standalone PHP checks can run in any disposable PHP 8.3 container that has the
helper and test files mounted:

```sh
php tests/recovery.php scripts/atelier-state.php
php -d memory_limit=128M tests/recovery-memory.php scripts/atelier-state.php
```

The memory test creates 60 MiB of temporary synthetic files; no customer data is
read. Recovery tests require OpenSSL and zlib. The language check needs a local
WordPress installation with the mu-plugin active:

```sh
wp eval-file tests/language.php --path=/path/to/local/wordpress
```

## Browser and performance

Use Node.js with Playwright and Chromium installed. Set `CHROMIUM_PATH` only if
using an existing browser executable instead of Playwright's bundled browser.
`NODE_PATH` can point to an already installed Playwright package directory.

```sh
ATELIER_TEST_URL=http://127.0.0.1:18086 node tests/storefront.cjs
ATELIER_TEST_URL=http://127.0.0.1:18086 node tests/performance.cjs
```

The storefront check accepts localhost only because it creates a fictional COD
order. It checks seven page types in both languages at 320/390/1440 px, dynamic
menu/filter labels, lazy images after scrolling, product JSON, no-results reset,
AJAX cart, quantity updates, invalid checkout and a completed test order.
`ATELIER_SCREENSHOTS` enables screenshot output. No live payments are made.

The performance check makes read-only requests. It uses three fresh browser
contexts, disabled browser cache, a 390 px viewport, 150 ms latency, 200 kB/s
download and 4x CPU throttling. Compare on the same warmed server; record TTFB
as well as LCP so changes in server load are not mistaken for frontend gains.
`ATELIER_PERFORMANCE_OUTPUT` optionally saves JSON outside the repository.
