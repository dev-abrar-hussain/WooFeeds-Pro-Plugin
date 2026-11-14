# WooFeeds Pro - Canonical Project Plan

This document is the durable source of truth for the WooFeeds Pro project. The
original PDF is no longer required for implementation. If later instructions
conflict with this document, the user's latest explicit instruction wins.

## Product Goal

Build a production-ready WooCommerce plugin that automatically generates and
hosts product feeds for:

- Google Merchant Center
- Meta Commerce Manager
- ChatGPT Business Hub

The plugin must be beginner-friendly, efficient on normal WordPress hosting,
and protected by a one-time-payment, domain-locked licensing system.

## 1. Feed Generation Engine

- Read WooCommerce products through supported WooCommerce APIs, primarily
  `wc_get_products()` and product objects.
- Use an extensible OOP design with a shared abstract `FeedGenerator` and
  separate platform generators.
- Generate these platform formats:
  - Google Merchant: RSS 2.0 XML, with support for required fields including
    ID, title, description, link, image link, price, availability, condition,
    brand, GTIN, and MPN.
  - Meta Commerce Manager: XML or CSV using the Google-like catalog schema,
    including Google and Facebook product categories where applicable.
  - ChatGPT Business Hub: gzip-compressed JSONL using a schema.org Product-like
    object per line.
- Generate feeds on a schedule through WP-Cron, with a design that can also be
  triggered by a real server cron.
- Write static files so public feed requests do not query the database.

## 2. Hosted Feed Endpoints

Canonical public URL patterns:

- `/wc-feeds/google-merchant.xml`
- `/wc-feeds/meta-commerce.xml`
- `/wc-feeds/chatgpt-products.jsonl.gz`

Generated files are stored under `wp-content/uploads/wc-feeds/`. Rewrite rules
or safe endpoints expose those files without generating feeds on request.

## 3. Beginner-Friendly WordPress Admin

Create a settings area with these sections:

- Dashboard
  - Feed status
  - Last generated time
  - Product count
  - Regenerate Now action
- Google Merchant
  - Enable/disable toggle
  - Copy feed URL action
  - Platform-specific field settings and missing-field warnings
- Meta Commerce
  - Enable/disable toggle
  - Copy feed URL action
  - Platform-specific field settings and warnings
- ChatGPT Business Hub
  - Enable/disable toggle
  - Copy feed URL action
  - Compression settings and warnings
- Field Mapping
  - Map required feed fields to WooCommerce fields, product attributes, or
    custom fields
  - Important mappings include brand, GTIN, MPN, and category
- Validation Checker
  - Validate required values, image URLs, and other feed requirements
  - Present plain-language results such as "15 products are missing GTIN"

All mutations must use WordPress capabilities and nonces. Admin text must be
translatable and understandable to non-technical store owners.

## 4. Domain-Locked Licensing

### License service

Build a small standalone API, deployable on the Sahoolat server, with endpoints
or equivalent operations to:

- Issue a license after a successful purchase
- Activate a license and lock it to the first domain
- Validate that subsequent checks come from the same domain
- Reject activation on a different domain with a clear error
- Deactivate a domain so a customer can migrate or move staging to production
- Record license status and relevant audit information in MySQL or PostgreSQL

### Plugin-side license client

- Provide an activation screen for entering a license key.
- Send the license key, normalized domain, and site URL to the license service.
- Store license status securely using WordPress options/transients.
- Revalidate every 24-48 hours.
- Include an offline grace period so a temporary licensing-server outage does
  not immediately break a customer's feeds.
- Provide a Deactivate action for legitimate migrations.
- Display the explicit error: "This license is already active on another
  domain" when applicable.

The plugin must fail safely and avoid deleting already-generated customer data
when a license check cannot be completed.

## 5. Technology Direction

- WordPress plugin: namespaced PHP, OOP, WordPress and WooCommerce coding and
  security standards.
- License service: small PHP/Laravel or Node/Express API with MySQL/PostgreSQL.
- Payments: a merchant-of-record provider supporting one-time purchases and
  webhook-driven license issuance, preferably Lemon Squeezy or Paddle.
- Deployment target for the license API: Sahoolat server.

## 6. Development Phases

### Phase 1 - Core Feed Engine

- WooCommerce product fetching
- Abstract generator contract and platform generator classes
- Scheduled and manual static-file generation
- Public feed URL/rewrite handling

### Phase 2 - Admin Experience

- Settings pages and tabs
- Field-mapping interface
- Feed status dashboard
- Validation checker and plain-language warnings

### Phase 3 - Licensing

- License API for issue, activate, validate, and deactivate
- Domain locking
- Plugin activation and license-status UI
- Cached validation and offline grace-period behavior

### Phase 4 - Payment Integration

- Lemon Squeezy or Paddle webhook verification
- Automatic license generation after successful one-time payment
- Buyer license delivery/email integration
- Buy Now/checkout integration points

### Phase 5 - Production Polish

- Structured error handling and logging
- Beginner documentation and tooltips
- Automated tests and WordPress/WooCommerce compatibility checks
- Packaging and release workflow
- Optional self-hosted updater or integration with an established updater

## 7. Product Website and Distribution

- The canonical product website is `https://soft.sahoolat.pk/`.
- Host a fast static marketing site with product features, channel-specific
  setup guides, licensing guidance, troubleshooting, and direct release
  downloads.
- Maintain search metadata, canonical URLs, structured data, `robots.txt`, and
  an XML sitemap as part of each website release.
- Deploy website builds to the Sahoolat server as versioned releases behind
  Nginx, with `/var/www/soft.sahoolat.pk/current` pointing to the active build.
- Serve HTTPS with an automatically renewed Let's Encrypt certificate and
  redirect HTTP traffic to HTTPS.
- The initial website and version `0.1.0` plugin download were deployed and
  externally verified on 2026-11-14.

## 8. Pending Product Decisions

Do not silently choose these. Record the eventual decision here when confirmed:

1. Payment provider: Lemon Squeezy, Paddle, or another approved provider.
2. Exact license-service hosting/deployment details on the Sahoolat server.
3. Distribution model: direct-sale closed-source, or WordPress.org free plus
   paid Pro.
4. Whether the optional self-hosted updater is part of the first public release.

## 9. Definition of Done

The project is complete when:

- All three feed formats are generated from real WooCommerce products.
- Static feed files refresh automatically and are available at stable URLs.
- Mapping, validation, manual regeneration, status, and license workflows are
  usable by a beginner.
- License issuance, domain activation, validation, grace period, and
  deactivation work end to end.
- The chosen payment provider can issue licenses through verified webhooks.
- Security, escaping, capabilities, nonces, privacy, and failure modes have
  been reviewed.
- Automated tests cover core generators, licensing rules, and important admin
  actions.
- Installation, configuration, troubleshooting, deployment, and release
  documentation are included.
- The product website, public documentation, SEO files, HTTPS, and current
  release download are available from `soft.sahoolat.pk`.

