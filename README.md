# Atelier — WordPress + WooCommerce demo

An original, responsive WooCommerce storefront for a fictional independent homeware studio. The shop runs on real WordPress and WooCommerce; it is not a static HTML mockup. Products, filters, cart, checkout, order creation, account and editorial pages use WooCommerce and WordPress.

## Local launch

Requirements: Docker Desktop with Compose. From this folder:

```sh
docker compose up -d
```

On a fresh clone, open <http://localhost:8080>, complete the WordPress installer, then install and activate **WooCommerce** (the official plugin) in **Plugins → Add New Plugin** and activate **Atelier Shop** in **Appearance → Themes**. On first theme activation the demo creates its pages, product catalog and product attributes. Its local Apache configuration supports normal WordPress permalinks.

The already initialized local instance uses `atelier_admin` as its administrator; its password was created for this local demo and provided in the handoff chat. Change it in **Users → Profile** if you keep using this instance. WooCommerce is active, sample products are loaded, store visibility is enabled, and offline **Cash on delivery** is enabled for test orders. No real payment provider is configured. Set currency, country, shipping and tax in WooCommerce settings to match your demo scenario.

The built-in catalog seed runs once and adds 12 fictional homeware products with prices, categories, colors and materials. WooCommerce handles category browsing, search, sorting, pagination, product detail, cart, checkout and account. Shop filters are provided by the theme for category, price, color and material. The checkout creates an order in WooCommerce; keep payments in an offline/test mode.

To stop: `docker compose down`. To erase the local database and uploads as well: `docker compose down -v`.

## What is included

- Custom WordPress theme with editorial home page, shop, about, journal, contact and FAQ pages.
- WooCommerce product templates, responsive archive and product details, filter sidebar/drawer, cart and checkout styling.
- One-time sample catalog provisioning and sensible WooCommerce defaults.
- Docker Compose with MariaDB and persistent local volumes.

All brand names, products, prices and copy are fictional demo content. Replace them before any public launch.

## Deployment outline

1. Provision a host with current PHP supported by WordPress, MySQL/MariaDB, HTTPS and persistent media storage (or use a managed WordPress host).
2. Install WordPress and WooCommerce. Upload `wp-content/themes/atelier-shop` and activate Atelier Shop.
3. Add real products and product attributes in WooCommerce; replace demo copy and imagery, configure shipping/tax, privacy and legal pages.
4. Configure a payment provider in its sandbox first, test orders/refunds/webhooks, then switch to live credentials only when ready.
5. Enable HTTPS, backups, updates and production caching. Do not deploy the Docker sample database credentials to a public server.

This workspace contains a local demo source project. It has not been published to a public host or pushed to GitHub.
