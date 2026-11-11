# WooCommerce Marketplace submission checklist – Pro add-on

**Product:** Checkout Impact Regression Pro for WooCommerce · **Folder/slug:** `checkout-impact-regression-pro-for-woocommerce` · **Version:** 1.0.0
**Depends on:** free plugin `checkout-impact-regression-for-woocommerce` (WordPress.org) and WooCommerce.
**Package:** `dist/checkout-impact-regression-pro-for-woocommerce-1.0.0.zip` (built by `./bin/build.sh` / CI artifact).

## What Pro adds

- **Change alerts:** one email digest (15-minute batching, up to 5 recipients) when a plugin update or payment/shipping setting change is recorded. Setting values are never included.
- **Report export:** CSV and JSON per scenario, with spreadsheet-formula neutralisation.
- New **Alerts** tab and export buttons inside the free plugin's screen (public hooks only; no changes to free code paths).

## Ready in the repository

| Item | State |
|---|---|
| Separate plugin with WooCommerce headers, `Requires Plugins`, HPOS + Cart/Checkout blocks declarations | Done |
| Capability checks on every REST route; translatable strings (`checkout-impact-regression-pro-for-woocommerce`) | Done |
| Uninstall removes only Pro options and the scheduled digest | Done |
| Runtime tests on WP 7.1.4 / Woo 11.2.2 (alerts queue → digest mail, export download) | Done |
| Contract test `tests/pro-contract.php`, `tests/free-api-contract.php`, `tests/integration/` | Done |

## Owner steps (need your WooCommerce.com account)

1. Apply as a vendor: WooCommerce.com → *Sell on WooCommerce* (vendor application). Mention WPEssential and the free WordPress.org plugin.
2. After vendor approval, create the product in the Vendor Dashboard and upload the Pro ZIP.
3. Run **QIT** (Quality Insights Toolkit) tests offered in the Vendor Dashboard (activation, security, PHP compatibility, WooCommerce E2E) and fix findings.
4. Provide listing content: name, short description (use "What Pro adds" above), screenshots (Alerts tab, Reports with export), documentation URL (`https://wpessential.org/plugins/checkout-impact-regression-pro-for-woocommerce`), support policy and pricing (open question in the plan).
5. Add the `Woo:` product header the dashboard gives you after approval to `checkout-impact-regression-pro-for-woocommerce.php` (format `Woo: <product_id>:<hash>`) and rebuild – this lets WooCommerce.com deliver updates.
6. Confirm with the review team that requiring the free WordPress.org plugin is acceptable (open question in the plan).
