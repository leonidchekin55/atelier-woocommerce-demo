# Free recovery for the Render portfolio demo

The published demo uses a private GitHub repository containing encrypted database recovery snapshots. This is recovery storage for a small demonstration, not a remotely hosted MySQL database or an unlimited backup service. MariaDB still runs inside Render's ephemeral container.

## What survives container replacement

- WooCommerce orders, IDs, statuses and order items.
- Private contact messages and newsletter subscriptions.
- WordPress users, pages, product data and settings included in the database.
- Bundled catalog photographs: the bootstrap reconstructs missing files while preserving attachment IDs.
- Arbitrary files in `wp-content/uploads`, including user-uploaded product photos.

Arbitrary uploaded files are **not** stored in the database snapshot. Keep a separate media backup before adding original uploads. This setup uses one instance and is unsuitable for a busy or production shop.

## Write and restore protocol

1. Startup fetches the private repository and authenticates/decrypts its latest snapshot before serving WordPress. Database and media archives use separate AES-256-GCM additional-authentication domains. Missing credentials, missing database snapshots or failed restore stop startup; there is no silent empty-shop fallback.
2. PHP requests share a lock. Before a POST, scheduled task or order cancellation, the latest remote snapshot is synchronized before WordPress loads. A stale instance cannot overwrite a newer remote commit.
3. Checkout and contact/newsletter handlers save synchronously before acknowledging success. Orders and selected admin edits/deletions also trigger recovery saves. If a required save fails, checkout/forms return an error; visitors must retry.
4. A SQL dump and a sorted, path-validated archive of uploads are compressed, authenticated and encrypted using AES-256-GCM with fresh nonces. Only `state.enc` and `media.enc` are committed. SQL temporary files are removed after export. Media restore rejects symbolic links, absolute paths, traversal, oversized archives, and oversized file counts; files are written atomically under the uploads directory.
5. The dashboard widget reports the most recent successful save time in UTC and the demo media capacity.

Recovery preserves the snapshot state, not every arbitrary database change made by third-party plugins. WordPress/WooCommerce sessions and background bookkeeping are captured in subsequent saves. There is no scheduled keep-alive or guarantee of immediate uptime on Render Free.

## Secrets and configuration

Store these **only** in the hosting provider's secret environment settings:

- `ATELIER_STATE_REPO`: `ssh://git@ssh.github.com:443/OWNER/PRIVATE-REPO.git`.
- `ATELIER_STATE_KEY`: base64 encoding of a cryptographically random 32-byte AES key.
- `ATELIER_STATE_SSH_KEY_B64`: base64 encoding of the private OpenSSH deploy key. Give the corresponding public key write access to this one private repository only.
- Stable `WORDPRESS_AUTH_KEY`, `WORDPRESS_SECURE_AUTH_KEY`, `WORDPRESS_LOGGED_IN_KEY`, `WORDPRESS_NONCE_KEY`, and the four matching `_SALT` values.
- Existing database/admin environment settings described in README.

Never commit these values or put them in frontend JavaScript. Store a separate secure copy of the encryption key: a lost key makes recovery impossible. GitHub SSH host keys are pinned in `scripts/github-known-hosts`; update them from GitHub's official metadata when they rotate.

The repository must already contain a compatible `state.enc` on branch `main`. Initialize it through an authenticated database export; a blank repository deliberately fails restore. `ATELIER_STATE_BRANCH` can select an isolated verification branch. Run `php /usr/local/lib/atelier-state.php restore` or `save` inside the configured container for recovery operations; do not run concurrent CLI operations while requests are being served.

## Limits and operating notes

The encrypted compressed database snapshot limit is 5 MiB. The encrypted media archive limit is 20 MiB and the expanded archive limit is 64 MiB. Media is stored outside the database but in the same atomic Git snapshot commit. Every save creates a snapshot; after ten reachable snapshots the branch is compacted to the current encrypted database and media state using a force-with-lease update. This limits reachable history to roughly ten snapshots. Prior commits become unreachable from the private branch; GitHub may retain unreachable objects until its own garbage collection completes. Keep traffic low and migrate to proper external database/media storage if usage grows. This feature adds a network round trip to writes and can become temporarily unavailable when GitHub or its SSH endpoint is unavailable.

Private repository access and the encryption key are separate credentials. Database snapshots still contain customer data after decryption; use fictional data in this demonstration. Deleting a record does not remove it from up to ten reachable prior snapshots; branch compaction makes older snapshots unreachable, but does not guarantee immediate physical erasure by GitHub. Before accepting real personal data, replace this storage arrangement with an appropriate database and a defined retention/deletion policy.

## Languages

English is the default. Visitors can switch to Russian or English using the header control; the choice is saved in a first-party cookie. Official Russian WordPress and WooCommerce translations are bundled into the image, while theme copy has a Russian storefront dictionary. Admin pages remain in the configured WordPress language.

Render Free remains ephemeral and may sleep: <https://render.com/docs/free>. GitHub's service rules apply: <https://docs.github.com/en/site-policy/acceptable-use-policies/github-acceptable-use-policies>.
