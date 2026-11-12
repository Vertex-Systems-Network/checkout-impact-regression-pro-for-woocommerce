# Checkout Impact Regression Pro for WooCommerce

By [WPEssential](https://wpessential.org) · Plugin page: <https://wpessential.org/plugins/checkout-impact-regression-pro-for-woocommerce>

Paid add-on (WooCommerce Marketplace) for the free **[Checkout Impact Regression for WooCommerce](https://github.com/Vertex-Systems-Network/checkout-impact-regression-for-woocommerce)** plugin (WordPress.org).

- **Change alerts:** one email digest when a plugin update or a payment/shipping setting change is recorded (15-minute batching, up to 5 recipients, retries, no setting values in mail).
- **Report export:** CSV and JSON for every saved comparison report of a scenario.

## Free + Pro: how they fit together

| | Free | Pro |
|---|---|---|
| Repository | `checkout-impact-regression-for-woocommerce` | `checkout-impact-regression-pro-for-woocommerce` (this repo) |
| Distribution | WordPress.org | WooCommerce Marketplace |
| Works alone | Yes – fully useful | **No** – needs the free plugin |
| Contains the other's code | No | No |

- `Requires Plugins: woocommerce, checkout-impact-regression-for-woocommerce` – WordPress will not activate Pro without the free plugin, and will not let the free plugin be deactivated while Pro is active.
- Pro also checks `WPECIR_PRO_MIN_FREE_VERSION` against the free plugin's `WPECIR_VERSION` and stays idle with an admin notice when the free plugin is too old.
- Pro uses only the free plugin's public extension points: `wpecir_loaded`, `wpecir_change_event_recorded`, `wpecir_pro_active`, the `Repository` API, and the JS filters `wpecir.adminTabs` / `wpecir.reportActions`. `tests/free-api-contract.php` lists every one of them.

## Keeping the two in sync

Both repositories run the same checks against each other on every push, pull request and every night:

1. `tests/free-api-contract.php` – fails if the free plugin no longer provides something Pro uses (or Pro starts using something new).
2. `tests/integration/run.sh` – installs the latest WordPress + WooCommerce in WordPress Playground, activates free + Pro and checks REST routes, export, alerts, permissions and that Pro cannot run without free.
3. This (private) repository's CI builds **both** ZIPs into one **plugin-packages** artifact that expires after 7 days. GitHub lets anyone with read access to a repository download its artifacts, so **give access to this repository only to the owner/admins** – no read-only collaborators, outside collaborators or broad team grants. The public free repository never builds or uploads Pro files; it tests Pro inside the job using the read-only `PRO_REPO_TOKEN` secret.

If both repositories have a branch with the same name, CI tests those branches together – use that for changes that touch both plugins. **Keep this repository private.** While it is public, CI still builds and checks the Pro ZIP but does not upload it.

Optional: add a `CROSS_REPO_TOKEN` secret (fine-grained token with *Contents: read & write* on both repos) to make a push to `main` in one repo immediately re-run CI in the other.

## Development

```bash
git clone https://github.com/Vertex-Systems-Network/checkout-impact-regression-for-woocommerce ../checkout-impact-regression-for-woocommerce
php tests/pro-contract.php
php tests/free-api-contract.php                 # FREE_DIR defaults to ../checkout-impact-regression-for-woocommerce
./bin/build.sh                                   # dist/: Pro ZIP + free ZIP
tests/integration/run.sh <free-build-dir> <pro-build-dir> 8.2
```

Edit `wordpress-org/readme.md`, then run `php tools/readme-to-txt.php` (same readme system as WPEssential). Release steps: [docs/WOO-MARKETPLACE-SUBMISSION.md](docs/WOO-MARKETPLACE-SUBMISSION.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
