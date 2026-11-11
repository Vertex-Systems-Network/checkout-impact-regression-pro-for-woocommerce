#!/usr/bin/env bash
# Runs the free + Pro integration check in WordPress Playground (php-wasm, SQLite).
# Usage: tests/integration/run.sh <free-build-dir> <pro-build-dir> [php-version]
# CI uses the latest WordPress and WooCommerce from WordPress.org.
# Offline: set PLAYGROUND_WP_DIR to a WordPress directory that already contains WooCommerce
# (and remove the installPlugin step with PLAYGROUND_SKIP_WOO_INSTALL=1).
set -euo pipefail
FREE=$(realpath "$1"); PRO=$(realpath "$2"); PHP_VERSION="${3:-8.2}"
HERE=$(cd "$(dirname "$0")" && pwd)
BLUEPRINT="$HERE/blueprint.json"
if [ "${PLAYGROUND_SKIP_WOO_INSTALL:-0}" = 1 ]; then
	BLUEPRINT=$(mktemp --suffix=.json)
	python3 - "$HERE/blueprint.json" "$BLUEPRINT" <<'PY'
import json,sys
b=json.load(open(sys.argv[1])); b['steps']=[s for s in b['steps'] if s['step']!='installPlugin']
b['steps'].insert(0,{"step":"activatePlugin","pluginPath":"woocommerce/woocommerce.php"})
json.dump(b,open(sys.argv[2],'w'))
PY
fi
ARGS=(--php="$PHP_VERSION" --blueprint="$BLUEPRINT"
	--mount="$FREE:/wordpress/wp-content/plugins/checkout-impact-regression-for-woocommerce"
	--mount="$PRO:/wordpress/wp-content/plugins/checkout-impact-regression-pro-for-woocommerce"
	--mount="$HERE:/integration")
if [ -n "${PLAYGROUND_WP_DIR:-}" ]; then ARGS+=(--auto-mount="$PLAYGROUND_WP_DIR"); fi
rm -f "$HERE/result.txt"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@latest}"
set +e
OUT=$($CLI run-blueprint "${ARGS[@]}" 2>&1); STATUS=$?
set -e
if [ -f "$HERE/result.txt" ]; then cat "$HERE/result.txt"; fi
if ! grep -q "INTEGRATION OK" "$HERE/result.txt" 2>/dev/null; then
	echo "$OUT" | grep -v EBADENGINE | tail -30
	echo "Integration check did not complete (playground exit $STATUS)"
	exit 1
fi
