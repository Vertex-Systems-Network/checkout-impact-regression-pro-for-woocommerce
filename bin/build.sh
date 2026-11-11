#!/usr/bin/env bash
# Builds dist/checkout-impact-regression-pro-for-woocommerce-<version>.zip (WooCommerce Marketplace).
# Pro only works together with the free plugin, so by default this also builds the
# free ZIP from FREE_DIR (a checkout of checkout-impact-regression-for-woocommerce,
# default ../checkout-impact-regression-for-woocommerce) into the same dist/.
#   bin/build.sh             Pro + free
#   bin/build.sh --pro-only  Pro only (used by the free repository's build)
set -euo pipefail
cd "$(dirname "$0")/.."

SLUG=checkout-impact-regression-pro-for-woocommerce
FREE_DIR="${FREE_DIR:-../checkout-impact-regression-for-woocommerce}"
version() { grep -m1 -E '^\s*\*\s*Version:' "$1" | sed -E 's/.*Version:\s*//' | tr -d '[:space:]'; }
VERSION=$(version "$SLUG.php")
CONST=$(grep -oE "WPECIR_PRO_VERSION', '[^']+'" "$SLUG.php" | grep -oE "[0-9][^']*")
[ "$VERSION" = "$CONST" ] || { echo "WPECIR_PRO_VERSION ($CONST) != plugin Version ($VERSION)"; exit 1; }
php tools/readme-to-txt.php --check

rm -rf build/$SLUG && mkdir -p build/$SLUG dist
rm -f dist/$SLUG-*.zip dist/$SLUG-*.zip.sha256
cp -R "$SLUG.php" uninstall.php readme.txt LICENSE assets includes build/$SLUG/
find build -name '.DS_Store' -delete
(cd build && zip -qr ../dist/$SLUG-$VERSION.zip $SLUG)
unzip -tq dist/$SLUG-$VERSION.zip >/dev/null
(cd dist && sha256sum $SLUG-$VERSION.zip > $SLUG-$VERSION.zip.sha256)
unzip -Z1 dist/$SLUG-$VERSION.zip | grep -qx "$SLUG/$SLUG.php" || { echo "missing main file"; exit 1; }
if unzip -Z1 dist/$SLUG-$VERSION.zip | grep -qE '/(tests|tools|\.github)/|AGENTS\.md|CLAUDE\.md|vendor/'; then echo "dev files leaked into the Pro ZIP"; exit 1; fi

if [ "${1:-}" != "--pro-only" ]; then
	[ -x "$FREE_DIR/bin/build.sh" ] || { echo "Free plugin checkout not found at $FREE_DIR (set FREE_DIR)."; exit 1; }
	FREE_DIR=$(realpath "$FREE_DIR")
	"$FREE_DIR/bin/build.sh" >/dev/null
	cp "$FREE_DIR"/dist/checkout-impact-regression-for-woocommerce-*.zip* dist/
fi
ls -lh dist
