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

Requires Docker and Python 3; ports 18087–18091 must be free. The test replaces
the entire donor container/database and removes all containers and temporary
recovery data afterward. It verifies a native WooCommerce order, private inbox
message/subscriber, newly uploaded attachment and generated thumbnails, refusal
of missing/corrupt media or a symlink in uploads before SQL import, one-time
migration of files from the previous persistent-disk uploads path (a container
restart proves deleted files stay deleted, and shell checks cover interrupted
migrations resuming without overwriting newer files), startup recovery of the old
tree after an interrupted directory swap, and an upgrade check that prefers a
verified snapshot over stale legacy files even if `current` already existed. It
verifies that product searches enqueue commerce styles without styling regular
post searches. It
also applies a valid database change followed by invalid SQL and checks that
the previous value is restored before startup continues. A final isolated
startup uses fictional Brevo settings and verifies that FluentSMTP resolves the configured provider
and WooCommerce enables the owner notification with the configured recipient,
without making any external email request.
The recovery fixture also verifies the IDs and hashes of all twelve product
images and the presence of their generated thumbnails after restoration.
The standalone recovery checks also exercise authoritative replacement where
existing files and folders have the opposite types from the snapshot.

Standalone PHP checks can run in any disposable PHP 8.3 container that has the
helper and test files mounted:

```sh
php -d auto_prepend_file= tests/recovery.php scripts/atelier-state.php
php -d auto_prepend_file= -d memory_limit=128M tests/recovery-memory.php scripts/atelier-state.php save
php -d auto_prepend_file= -d memory_limit=128M tests/recovery-memory.php scripts/atelier-state.php restore
```

The memory test creates 60 MiB of temporary synthetic files. Its `save` phase
archives them; its separate `restore` phase stages them before allocating a
64 MiB SQL buffer. No customer data is read. Recovery tests require
OpenSSL and zlib. The language check needs a local
WordPress installation with the mu-plugin active:

```sh
wp eval-file tests/language.php --path=/path/to/local/wordpress
```

WooCommerce transactional email checks run on a disposable local store. The
copy check validates sender/subject branding, order instructions, site links,
mobile styles, and that unrelated account emails are untouched. The lifecycle
check creates two fictional COD orders, intercepts every `wp_mail` call before
delivery, verifies owner/customer notices for processing, cancellation, and
completion, then permanently deletes both orders:

```sh
wp eval-file tests/email-copy.php --path=/path/to/local/wordpress
wp eval-file tests/order-emails.php --path=/path/to/local/wordpress
```

## Browser and performance

`wp eval-file tests/product-media.php --path=/path/to/local/wordpress` exercises
managed-image migration, missing-source fallback, repeat runs, meaningful alt
text and preservation of a user's replacement image. It refuses public hosts;
run only in a disposable local store with the bundled product images present.

`wp eval-file tests/price-filters.php --path=/path/to/local/wordpress` checks
that empty price bounds are omitted while explicit zero and decimal bounds
remain unchanged. Search-only filter submissions must still find products.

`tests/catalog-filters.cjs` makes read-only catalog requests and checks EN/RU at
320/1440 px: individual filter removal, multiple values, decimal prices,
sorting/language/category preservation and malformed parameters. Run with the
same Node/Playwright setup below. It also accepts the published demo URL.

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
