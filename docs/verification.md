# Storefront verification

Verified locally on 2026-10-10 using Chromium with real WordPress and WooCommerce.

- Home, shop, product and contact layouts fit 320, 390 and 1440 pixel viewports.
- The menu closes with Escape. Mobile filters keep keyboard focus inside the drawer, make background content inert, and restore focus when closed.
- All 12 products have real WordPress attachment IDs; the 9 bundled photos are reused without duplicating attachments. Product galleries load image files and include responsive image sources.
- Adding a product, changing cart quantity, and going to checkout work. A missing billing address is rejected.
- A fictional US address and cash-on-delivery created a WooCommerce order with a $188 total and $0 shipping. No live payment gateway was used.
- Newsletter submission succeeds and submitting the same address twice leaves one private subscriber record.
- The contact template now closes its textarea correctly. A browser submission succeeds and creates a private WordPress inbox message.
- Changed PHP files, JavaScript and the startup shell script pass syntax checks.

Render Free rebuilds the demo catalog and media library after losing its filesystem. Test orders, messages, subscribers and manual dashboard changes are disposable. The images are illustrative demo photography; a real store needs photographs of its actual products.


## Durable demo recovery — 2026-10-10

Verified in a fresh Docker container against an isolated private recovery branch:

- Existing public order 37, contact 38 and subscriber 39 restored from the migration snapshot.
- Created test COD order 44 ($188, $0 shipping, processing) and private contact 45 through the browser; newsletter duplicate handling passed.
- Deleted the entire container and its ephemeral database, then created a new container without volumes. Orders 37/44, messages 38/45 and subscriber 39 returned with identical IDs/statuses/totals; all product image files were rebuilt (zero missing).
- An unavailable recovery branch caused contact POST to return 503 before a success acknowledgement; no extra contact was stored.
- Authenticated encryption roundtrip and modified ciphertext rejection passed.
- Search plus material filter, malformed nested filter arguments, PHP syntax, shell syntax and whitespace checks passed. Catalog pagination shows nine products per first page.

See `persistence.md` for capacity, media and low-traffic limitations. Local browser scripts use fictional test data; no real payments or email deliveries were performed.

Public version 1.3.0 also passed 320/390/1440 layouts, keyboard controls, invalid checkout rejection, test COD order 44 ($188 with free shipping), contact submission and JavaScript-error checks. A missing custom attachment was verified locally to remain untouched by demo-photo reconstruction.
