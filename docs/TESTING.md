# Acceptance Testing

Automated repository checks run with `npm test`. Before a production release,
also complete this matrix in a disposable WordPress/WooCommerce installation.

## Plugin activation

- Activate with WooCommerce enabled; no PHP warning or fatal error appears.
- Disable WooCommerce; the dependency notice appears and the store remains
  usable.
- Confirm activation creates `wp-content/uploads/wc-feeds/` and schedules both
  cron hooks.

## Catalog fixtures

Create at least:

- One simple in-stock product with SKU, price, description, brand, valid GTIN,
  MPN, category, and gallery images.
- One sale product.
- One out-of-stock product.
- One variable product with at least two visible variations.
- One deliberately incomplete product for validation warnings.

## Feed generation

- Activate a test license or enable the development constant locally.
- Generate all channels and confirm the dashboard counts.
- Parse both XML files and decompress/parse every JSONL line.
- Confirm prices include a three-letter currency, URLs are absolute, variants
  share a group ID, and special characters remain valid UTF-8.
- Request each public URL twice and confirm the second request can return 304
  with the ETag.
- Disable a channel and confirm the next generation does not replace it.
- Simulate a generator failure and confirm the previous completed file remains.

## Admin and validation

- Save every tab with one toggle unchecked and confirm unrelated settings are
  preserved.
- Test brand, GTIN, and MPN from an attribute and from a custom field.
- Run validation and compare every counter with the fixtures.
- Log in as a user without `manage_woocommerce` and confirm all actions are
  denied.
- Submit actions without a nonce and confirm WordPress rejects them.

## Licensing

- Issue a key, activate on domain A, and validate it.
- Attempt activation on domain B and confirm the domain-lock error.
- Deactivate on A, then activate on B.
- Stop the license service and confirm a previously valid key enters grace.
- Advance beyond the grace period and confirm generation is blocked while
  existing feed files remain available.
- Mark a license refunded and confirm the next check invalidates it.

## Payment adapters

- Use provider webhook simulators, not handcrafted production requests.
- Confirm invalid signatures return 401.
- Replay the same successful event and confirm only one license exists.
- Confirm an unallowlisted product or variant is ignored.
- Confirm the buyer receives the activation email.
- Send an approved refund event and confirm the license becomes refunded.

