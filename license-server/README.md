# WooFeeds Pro License Server

This service issues one-time WooFeeds Pro keys, locks the first activation to a
normalized domain, validates subsequent checks, supports deliberate
deactivation, and disables licenses after supported refund webhooks.

## Requirements

- Node.js 20 or newer
- PostgreSQL 14 or newer
- HTTPS reverse proxy
- SMTP account for buyer delivery

## Setup

```bash
cp .env.example .env
npm install
npm run migrate
npm start
```

Generate strong, independent values for `ADMIN_API_TOKEN` and
`LICENSE_ENCRYPTION_KEY`. Keep the encryption key backed up: the service uses it
to decrypt an existing key when a payment provider retries a webhook. Changing
it makes previously stored key ciphertext unreadable.

Set `DATABASE_SSL=true` only when the PostgreSQL server presents a certificate
trusted by the host. Put the service behind a reverse proxy that terminates TLS
and forwards the real client IP.

## API

All request and response bodies are JSON.

### Issue a license

`POST /v1/licenses/issue` requires
`Authorization: Bearer <ADMIN_API_TOKEN>`.

```json
{
  "email": "buyer@example.com",
  "provider": "manual",
  "provider_order_id": "invoice-1001",
  "send_email": true,
  "metadata": {}
}
```

### Activate, validate, and deactivate

- `POST /v1/licenses/activate`
- `POST /v1/licenses/validate`
- `POST /v1/licenses/deactivate`

```json
{
  "license_key": "WFP-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX",
  "domain": "example.com",
  "site_url": "https://example.com/",
  "plugin_version": "0.1.0"
}
```

The first activation stores the normalized domain. A different domain receives
HTTP 409 and `This license is already active on another domain`.

## Lemon Squeezy

Configure:

- `LEMONSQUEEZY_WEBHOOK_SECRET`
- `LEMONSQUEEZY_VARIANT_IDS` as an allowlisted comma-separated list

Webhook URL: `https://licenses.example.com/v1/webhooks/lemonsqueezy`

Subscribe to `order_created` and `order_refunded`. The raw request body is
verified against the hexadecimal HMAC-SHA256 value in `X-Signature` before any
license mutation occurs. Only a confirmed full refund disables a license;
partial refunds are recorded without revoking access.

## Paddle Billing

Configure:

- `PADDLE_WEBHOOK_SECRET`
- `PADDLE_API_KEY` so the service can retrieve a transaction customer's email
- `PADDLE_ENVIRONMENT=production` or `sandbox`
- `PADDLE_PRODUCT_IDS` as an allowlisted comma-separated list

Webhook URL: `https://licenses.example.com/v1/webhooks/paddle`

Subscribe to `transaction.completed`, `adjustment.created`, and
`adjustment.updated`. The service validates `Paddle-Signature` over the raw body
and enforces a five-minute replay window. Approved full-refund adjustments
disable the linked license.

## Email delivery

Set all SMTP variables in `.env`. Webhook processing fails with a retryable HTTP
500 if a key cannot be delivered, while provider-order uniqueness prevents a
second license from being created on retry.

## Security properties

- Only SHA-256 key hashes are used for lookups.
- Recoverable key material is encrypted with AES-256-GCM for safe webhook
  retries and buyer redelivery.
- Activation and validation changes run in database transactions with row
  locking.
- License endpoints are rate-limited.
- Provider webhooks require verified raw-body signatures.
- Payment product/variant allowlists prevent unrelated purchases from issuing a
  license.
- Audit events record activation, validation, rejection, and deactivation.

## Production operations

- Back up PostgreSQL and `LICENSE_ENCRYPTION_KEY` together.
- Rotate `ADMIN_API_TOKEN` if exposed.
- Monitor `/health`, HTTP 5xx rates, failed webhook deliveries, and SMTP errors.
- Restrict database network access to the license service.
- Retain application and reverse-proxy logs according to the business privacy
  policy.

