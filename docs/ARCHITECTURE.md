# Architecture

## WordPress plugin

`Plugin` composes the application after WooCommerce loads. Product reads are
batched by `ProductRepository`, normalized once per channel run by
`ProductDataMapper`, and streamed into one of three generator classes. Each
generator writes to a temporary file before `FeedManager` publishes it to the
stable target path.

`Scheduler` invokes generation through WP-Cron. Product changes mark snapshots
stale, while public requests only stream the last completed file through
`FeedEndpoint`; public traffic never triggers catalog generation.

The admin application uses capability checks, nonces, sanitized settings, and
escaped output. Validation reads the same normalized records as the generators,
so its warnings reflect the exported values.

`LicenseManager` stores local state, calls the license API over HTTPS, and
allows a configurable offline grace period only after a successful validation.
Explicit server rejection invalidates the local state; network failure does
not delete existing feeds.

## License service

The Node.js service stores license records, encrypted key material, webhook
idempotency records, and audit events in PostgreSQL. License lookups use a
normalized-key SHA-256 hash. Activation, validation, and deactivation lock the
license row inside a transaction.

Payment provider adapters verify signatures before processing. A unique
provider/order constraint prevents duplicate keys. SMTP sends the key to the
buyer, while encrypted recovery allows safe redelivery after a provider retry.

## Extension points

- Add feed channels by extending `AbstractFeedGenerator` and registering the
  generator in `FeedManager::generators()`.
- Add mapping sources in `AdminPage::mapping_sources()` and resolution logic in
  `ProductDataMapper::resolve_source()`.
- Add payment providers as separate verified webhook handlers that call
  `LicenseService::issue()` with a stable provider order ID.

