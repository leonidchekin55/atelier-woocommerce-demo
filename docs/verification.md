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

## Bilingual media recovery update — 2026-10-10

- Docker image builds with official Russian WordPress and WooCommerce translation packs. The PHP recovery helper and both mu-plugins pass `php -l` in the built image.
- A local temporary WordPress/WooCommerce container confirmed the English default, the Russian language cookie and `lang="ru-RU"`, translated storefront text, Russian product/category/material names, and that switching back leaves English intact.
- The media archive passed an authenticated encryption, compress/decompress, and file restore round trip. The published private recovery repository now contains `media.enc` alongside `state.enc`; uploaded media is encrypted before it leaves the container.
- A disposable local bare-Git simulation exercised the ten-snapshot threshold and force-with-lease compaction; the remote branch retained only the latest root snapshot.
- The public portfolio homepage links the demo and current Google Drive résumé; Russian and English case pages return HTTP 200.

## Search and social metadata — 2026-10-10

- The local WordPress storefront returned non-empty page descriptions and Open Graph/Twitter metadata for English and Russian home pages, a product, a product category and a product search.
- Product previews use that product's media-library image; catalog and search preview URLs resolve to the current archive/search URL rather than a product permalink.
- The production Docker image built successfully and the updated PHP theme file passed `php -l`.

## Language-specific crawl URLs — 2026-10-10

- Explicit `?atelier_lang=ru` and `?atelier_lang=en` requests return HTTP 200 without redirecting and set the language cookie. Subsequent product and category navigation preserves the selected language.
- Home, product, category and search responses each emit one canonical URL and three reciprocal `hreflang` links (`en-US`, `ru-RU`, `x-default`). Russian canonicals retain `?atelier_lang=ru`; English canonicals use the clean URL.
- Local checks confirmed the HTML language attribute, language-switch target, localized metadata and URLs in both language modes. Search Console indexing was not available to verify.

## Public sitemap cleanup — 2026-10-10

- WordPress sitemap queries exclude the WooCommerce cart, checkout and account pages, the unused sample page, and the default “Hello world” post.
- The users sitemap provider is disabled, and author archives receive `noindex` while remaining crawlable so search engines can process the directive.
- Local WordPress core query hooks returned only useful public pages and disabled the users provider. After Render deploy `a3f523f`, the sitemap index and child maps returned HTTP 200, listed 12 products and 3 product categories, and omitted cart, checkout, account, sample content, and the users sitemap. The public author archive returned HTTP 200 with `noindex`.

The user-facing demo uses fictional sample data and no real payment gateway. The new uploaded-file persistence and history compaction are small-demo recovery features; they do not make Render Free a production database or promise immediate physical erasure of unreachable Git objects.
