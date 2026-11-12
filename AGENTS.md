# AI contributor instructions – Pro add-on

This is the paid add-on. The canonical rules, plan and decisions live in the free plugin repository:
<https://github.com/Vertex-Systems-Network/checkout-impact-regression-for-woocommerce> (`AGENTS.md`, `docs/DEVELOPMENT-PLAN.md`). Read them first; they apply here too.

Pro-specific rules:

- Pro is paid: keep this repository private and never publish Pro ZIPs, builds or source anywhere public (including the free repository's artifacts).
- Pro never contains free-plugin code and never works without it. Keep `Requires Plugins` and the `WPECIR_PRO_MIN_FREE_VERSION` gate.
- Use only the free plugin's public extension points. If Pro needs something new, add it to the free plugin first, raise `WPECIR_PRO_MIN_FREE_VERSION`, and extend `tests/free-api-contract.php`.
- When a change touches both plugins, use the same branch name in both repositories so CI tests them together.
- Strings use the text domain `checkout-impact-regression-pro-for-woocommerce`.
- Edit `wordpress-org/readme.md`, never `readme.txt` directly; run `php tools/readme-to-txt.php`.
- Work on a branch and open a pull request. Resume prompts never authorise marketplace submissions or releases.
