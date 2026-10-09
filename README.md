# Atelier — WordPress + WooCommerce demo

An original, responsive WooCommerce storefront for a fictional independent homeware studio. The shop runs on real WordPress and WooCommerce; it is not a static HTML mockup. Products, filters, cart, checkout, order creation, account and editorial pages use WooCommerce and WordPress.

## Local launch

Requirements: Docker Desktop with Compose. From this folder:

```sh
docker compose up -d
```

On a fresh clone, open <http://localhost:8080>, complete the WordPress installer, then install and activate **WooCommerce** (the official plugin) in **Plugins → Add New Plugin** and activate **Atelier Shop** in **Appearance → Themes**. On first theme activation the demo creates its pages, product catalog and product attributes. Its local Apache configuration supports normal WordPress permalinks.

The already initialized local instance uses `atelier_admin` as its administrator; its local-only password was provided in the handoff chat. Change it in **Users → Profile** if you keep using this instance. WooCommerce is active, sample products are loaded, store visibility is enabled, and offline **Cash on delivery** is enabled for test orders. No real payment provider is configured. The demo shipping zone accepts US addresses at $8 standard delivery, with free delivery above $150. These are sample terms; no orders are fulfilled.

The built-in catalog seed runs once and adds 12 fictional homeware products with prices, categories, colors and materials. WooCommerce handles category browsing, search, sorting, pagination, product detail, cart, checkout and account. Shop filters are provided by the theme for category, price, color and material. The checkout creates an order in WooCommerce; keep payments in an offline/test mode.

To stop: `docker compose down`. To erase the local database and uploads as well: `docker compose down -v`.

## What is included

- Custom WordPress theme with editorial home page, shop, about, journal, contact and FAQ pages.
- WooCommerce product templates, responsive archive and product details, filter sidebar/drawer, cart and checkout styling.
- One-time sample catalog and demo checkout provisioning, including US shipping methods.
- Private WordPress inbox and newsletter list with consent, nonce checks and spam honeypot fields. Form submissions are stored in wp-admin; this demo does not send email.
- Product and editorial photos are bundled as optimized WebP assets under the [Unsplash License](https://unsplash.com/license). DM Sans and Playfair Display are self-hosted, with their SIL Open Font License texts included in `assets/fonts`.
- Docker Compose with MariaDB and persistent local volumes.

All brand names, products, prices and copy are fictional demo content. Replace them before any public launch.

## Deployment outline

### Render free preview

`Dockerfile` packages WordPress, WooCommerce, Atelier Shop, WP-CLI and MariaDB in one container. For the free Render preview, set `WORDPRESS_DB_PASSWORD` to a unique random value and set `ATELIER_ADMIN_USER`, `ATELIER_ADMIN_PASSWORD` (secret), and `ATELIER_SITE_URL`. On an empty database, the entrypoint installs WordPress, activates WooCommerce and Atelier Shop, and prepares the demo before Apache serves requests. Keep the admin password in Render's environment settings; never add it to this repository.

Render Free has an ephemeral filesystem, so database and uploaded files are lost after restarts, spin-downs or redeploys. The bootstrap recreates the fictional shop and its sample catalog automatically, but demo orders and dashboard entries do not persist. Use this only as a portfolio preview, never for a real store. For durable data, use a host with persistent storage and backups.

### Production checklist

1. Provision a host with current PHP supported by WordPress, MySQL/MariaDB, HTTPS and persistent media storage (or use managed WordPress hosting).
2. Install WordPress and WooCommerce. Upload `wp-content/themes/atelier-shop` and activate Atelier Shop.
3. Add real products and attributes; replace demo copy and imagery; configure shipping/tax, privacy and legal pages.
4. Configure a payment provider in sandbox mode first, test orders/refunds/webhooks, then switch to live credentials only when ready.
5. Enable HTTPS, backups, updates and production caching. Never deploy the Docker sample database credentials to a public server.

Source repository: <https://github.com/leonidchekin55/atelier-woocommerce-demo>. Public Render preview: <https://atelier-woocommerce-demo.onrender.com>. The Render Free preview is disposable and not a production store.
