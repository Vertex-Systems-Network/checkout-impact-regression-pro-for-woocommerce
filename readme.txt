=== Checkout Impact Regression Pro for WooCommerce ===
Contributors: wpessential
Tags: woocommerce, checkout, alerts, export, regression testing
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: woocommerce, checkout-impact-regression-for-woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Email alerts and CSV/JSON report export for Checkout Impact Regression for WooCommerce.

== Description ==

Adds two features to the free Checkout Impact Regression for WooCommerce plugin:

* **Change alerts** – one email when a plugin is updated or a payment or shipping setting changes, so you know when to compare your checkout scenarios. Changes within 15 minutes are grouped. Setting values are never sent.
* **Report export** – download saved comparison reports as CSV or JSON.

Settings: WooCommerce → Checkout Impact → Alerts. Export: Reports tab.

**Requires the free plugin.** Install and activate [Checkout Impact Regression for WooCommerce](https://wordpress.org/plugins/checkout-impact-regression-for-woocommerce/) first. WordPress will not activate Pro without it, and Pro checks the free plugin's version before it loads.

== External services ==

Pro does not connect to any external service. Alert emails are sent with your site's own `wp_mail()` setup to the addresses you enter.

== Installation ==

1. Install and activate WooCommerce and Checkout Impact Regression for WooCommerce.
2. Upload the Pro ZIP under Plugins → Add New → Upload Plugin and activate it.
3. Open WooCommerce → Checkout Impact → Alerts.

== Changelog ==

= 1.0.0 =
* First release: change alerts and report export.
