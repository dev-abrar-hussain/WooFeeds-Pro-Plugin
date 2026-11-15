=== WooFeeds Pro ===
Contributors: woofeedspro
Tags: woocommerce, product feed, google merchant, meta catalog, chatgpt
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.1.1
License: Proprietary

Generate hosted product feeds for Google Merchant Center, Meta Commerce Manager, and ChatGPT Business Hub.

== Description ==

WooFeeds Pro generates scheduled static catalog files from WooCommerce products.
It includes beginner-friendly field mapping, validation, feed status, and a
domain-locked licensing workflow.

== Installation ==

1. Upload the `woofeeds-pro` folder to `/wp-content/plugins/`.
2. Activate WooFeeds Pro from the Plugins screen.
3. Open WooCommerce > WooFeeds Pro.
4. Configure the license service and activate a key.
5. Configure fields and regenerate the enabled feeds.

== Frequently Asked Questions ==

= Where are feed files stored? =

Under `wp-content/uploads/wc-feeds/`. Public channel URLs remain under
`/wc-feeds/`.

= Does every feed request query WooCommerce? =

No. Products are read only during scheduled or manual generation. Feed requests
serve the completed static snapshot.

= What happens if the license server is temporarily offline? =

A previously validated license uses the configured grace period. Existing feed
files are preserved.

== Changelog ==

= 0.1.1 =

* Normalized ChatGPT weight and dimension units to the current feed schema.
* Added additional images to Meta catalog exports.
* Hardened feed-directory checks, webhook size handling, and uninstall cleanup.

= 0.1.0 =

* Initial implementation of Google, Meta, and ChatGPT feeds.
* Added admin mapping, validation, scheduling, and licensing.
* Added standalone license and payment-webhook service.

