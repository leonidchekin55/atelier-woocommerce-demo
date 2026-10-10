# Free recovery for the Render portfolio demo

The published demo uses a private GitHub repository containing encrypted database recovery snapshots. This is recovery storage for a small demonstration, not a remotely hosted MySQL database or an unlimited backup service. MariaDB still runs inside Render's ephemeral container.

## What survives container replacement

- WooCommerce orders, IDs, statuses and order items.
- Private contact messages and newsletter subscriptions.
- WordPress users, pages, product data and settings included in the database.
- Bundled catalog photographs: the bootstrap reconstructs missing files while preserving attachment IDs.

Arbitrary uploaded files are **not** stored in the database snapshot. Keep a separate media backup before adding original uploads. This setup uses one instance and is unsuitable for a busy or production shop.

## Write and restore protocol

1. Startup fetches the private repository and authenticates/decrypts its latest snapshot before serving WordPress. Missing credentials, missing snapshots or failed restore stop startup; there is no silent empty-shop fallback.
2. PHP requests share a lock. Before a POST, scheduled task or order cancellation, the latest remote snapshot is synchronized before WordPress loads. A stale instance cannot overwrite a newer remote commit.
3. Checkout and contact/newsletter handlers save synchronously before acknowledging success. Orders and selected admin edits/deletions also trigger recovery saves. If a required save fails, checkout/forms return an error; visitors must retry.
4. A SQL dump is compressed, authenticated and encrypted using AES-256-GCM with a fresh nonce. Only `state.enc` is committed. SQL temporary files are removed after export.
5. The dashboard widget reports the most recent successful save time in UTC.

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

The encrypted compressed snapshot limit is 5 MiB; the decompressed restore limit is 64 MiB. The initial demo snapshot is about 250 KiB. Each save creates a Git commit, so history grows over time. Keep traffic low, inspect repository size periodically, and migrate to a proper external database/media store if usage grows. This feature adds a network round trip to writes and can become temporarily unavailable when GitHub or its SSH endpoint is unavailable.

Private repository access and the encryption key are separate credentials. Database snapshots still contain customer data after decryption; use fictional data in this demonstration. Git history retains older snapshots, so deleting a record in WordPress does not purge historical recovery copies. Before accepting real personal data, replace this storage arrangement with an appropriate database and a defined retention/deletion policy.

Render Free remains ephemeral and may sleep: <https://render.com/docs/free>. GitHub's service rules apply: <https://docs.github.com/en/site-policy/acceptable-use-policies/github-acceptable-use-policies>.
