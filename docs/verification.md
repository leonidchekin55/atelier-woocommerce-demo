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
