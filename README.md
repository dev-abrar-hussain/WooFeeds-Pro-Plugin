# WooFeeds Pro

WooFeeds Pro is a production-oriented WooCommerce plugin that generates static
catalog feeds for Google Merchant Center, Meta Commerce Manager, and ChatGPT
Business Hub. It also includes a standalone PostgreSQL-backed license service
for one-time, domain-locked licenses.

- Product website and documentation: [soft.sahoolat.pk](https://soft.sahoolat.pk/)
- Website source: [dev-abrar-hussain/Soft-Sahoolat](https://github.com/dev-abrar-hussain/Soft-Sahoolat)

The canonical scope and definition of done are in [PROJECT_PLAN.md](PROJECT_PLAN.md).

## Included features

- Batched WooCommerce product and variation reads.
- Google Merchant RSS 2.0 XML.
- Meta Commerce Google-style XML.
- Gzip-compressed JSONL using OpenAI's stable Agentic Commerce product-feed
  schema.
- Static files written to `wp-content/uploads/wc-feeds/`.
- Stable public URLs under `/wc-feeds/` with cache headers and ETags.
- WP-Cron scheduling plus manual regeneration.
- Admin tabs for status, channel configuration, mapping, validation, and
  licensing.
- Brand, GTIN, and MPN mappings from attributes, product properties, or custom
  fields.
- Plain-language catalog validation counters.
- License activation, first-domain lock, daily revalidation, offline grace
  period, and deactivation.
- Standalone Node.js/PostgreSQL license API.
- Verified Lemon Squeezy and Paddle webhook adapters with idempotent license
  issuance and SMTP delivery.
- Refund handling that disables the corresponding license.

## WordPress requirements

- WordPress 6.4 or newer
- WooCommerce 8.0 or newer
- PHP 7.4 or newer
- PHP zlib extension for the ChatGPT `.jsonl.gz` feed
- HTTPS in production

## Install the plugin

1. Copy this repository's plugin files to
   `wp-content/plugins/woofeeds-pro/`. Do not copy `license-server`, `tools`,
   `node_modules`, or project-only documentation into production WordPress.
2. Activate **WooFeeds Pro** in WordPress.
3. Open **WooCommerce > WooFeeds Pro > License**.
4. Enter the HTTPS base URL of the deployed license server and activate a key.
5. Configure country/seller values, field mappings, and channel categories.
6. Run validation, fix important warnings, then select **Regenerate Now**.

Public feed URLs use these stable paths:

- `/wc-feeds/google-merchant.xml`
- `/wc-feeds/meta-commerce.xml`
- `/wc-feeds/chatgpt-products.jsonl.gz`

If a server disables WP-Cron, call WordPress cron from the system scheduler at
least as often as the selected feed refresh interval.

Servers with WP-CLI can run a direct real-cron command instead:

```bash
wp woofeeds generate --path=/path/to/wordpress
```

## Development mode

License enforcement can be bypassed only in a local development environment by
adding this constant to `wp-config.php`:

```php
define( 'WOOFEEDS_PRO_DEV_MODE', true );
```

Never enable that constant in a distributed build or production store.

## License service

See [license-server/README.md](license-server/README.md) for deployment,
environment, database, webhook, SMTP, and API instructions.

## Checks

From the repository root:

```bash
npm install
npm test
```

The test command parses every PHP file and runs the license server's
cryptography, domain-normalization, and provider-signature tests. Full
WordPress/WooCommerce acceptance tests still require a real WordPress test
installation; use [docs/TESTING.md](docs/TESTING.md).

## Business decisions still open

The code supports both Lemon Squeezy and Paddle, but production should enable
only the selected provider. The following remain explicit owner decisions:

- Lemon Squeezy or Paddle as the primary checkout.
- Final Sahoolat server hostname, PostgreSQL credentials, SMTP provider, and
  deployment process.
- Direct-sale-only distribution or WordPress.org free plus paid Pro.
- Whether a self-hosted auto-updater belongs in the first public release.

