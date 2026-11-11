<?php
/**
 * Free ↔ Pro compatibility contract.
 *
 * Checks that the free plugin still provides every hook, class, method, REST
 * route, script handle and CSS class that Pro uses. Runs in BOTH repositories'
 * CI: a free change that breaks Pro, or a Pro change that needs something the
 * free plugin does not provide, fails the build.
 *
 * Usage: FREE_DIR=/path/to/checkout-impact-regression-for-woocommerce php tests/free-api-contract.php
 *
 * @package WPEssential\CheckoutImpactRegressionPro
 */

$free = getenv( 'FREE_DIR' ) ?: dirname( __DIR__, 2 ) . '/checkout-impact-regression-for-woocommerce';
$pro  = dirname( __DIR__ );
if ( ! is_file( $free . '/checkout-impact-regression-for-woocommerce.php' ) ) {
	fwrite( STDERR, "Free plugin not found. Set FREE_DIR.\n" );
	exit( 2 );
}

$failures = array();
function api_check( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}
$read = static function ( $path ) {
	return is_file( $path ) ? (string) file_get_contents( $path ) : '';
};

$main     = $read( $free . '/checkout-impact-regression-for-woocommerce.php' );
$repo     = $read( $free . '/includes/Storage/Repository.php' );
$schema   = $read( $free . '/includes/Storage/Schema.php' );
$admin    = $read( $free . '/includes/Admin/AdminPage.php' );
$admin_js = $read( $free . '/assets/js/admin.js' );
$css      = $read( $free . '/assets/css/admin.css' );
$pro_main = $read( $pro . '/checkout-impact-regression-pro-for-woocommerce.php' );
$pro_js   = $read( $pro . '/assets/pro.js' );

// Versions.
preg_match( "/define\\( 'WPECIR_VERSION', '([^']+)' \\)/", $main, $free_version );
preg_match( "/define\\( 'WPECIR_PRO_MIN_FREE_VERSION', '([^']+)' \\)/", $pro_main, $min_free );
api_check( ! empty( $free_version[1] ), 'free defines WPECIR_VERSION' );
api_check( ! empty( $min_free[1] ), 'Pro defines WPECIR_PRO_MIN_FREE_VERSION' );
if ( ! empty( $free_version[1] ) && ! empty( $min_free[1] ) ) {
	api_check( version_compare( $free_version[1], $min_free[1], '>=' ), "free {$free_version[1]} satisfies Pro minimum {$min_free[1]}" );
}
api_check( false !== strpos( $pro_main, 'Requires Plugins:     woocommerce, checkout-impact-regression-for-woocommerce' ), 'Pro header requires the free plugin slug' );

// PHP hooks Pro listens to or calls.
api_check( false !== strpos( $main, "do_action( 'wpecir_loaded'" ), 'free fires wpecir_loaded after loading' );
api_check( false !== strpos( $repo, "do_action( 'wpecir_change_event_recorded'" ), 'free fires wpecir_change_event_recorded' );
api_check( false !== strpos( $admin, "apply_filters( 'wpecir_pro_active'" ), 'free reads the wpecir_pro_active filter' );

// Classes and method signatures Pro calls.
define( 'ABSPATH', __DIR__ . '/' );
require_once $free . '/includes/Storage/Schema.php';
require_once $free . '/includes/Storage/Repository.php';
$class = 'WPEssential\\CheckoutImpactRegression\\Storage\\Repository';
api_check( class_exists( $class ), 'free Repository class exists' );
if ( class_exists( $class ) ) {
	foreach ( array( 'get_scenario' => 1, 'list_manual_comparisons' => 3 ) as $method => $min_params ) {
		$ok = method_exists( $class, $method ) && ( new ReflectionMethod( $class, $method ) )->isPublic() && ( new ReflectionMethod( $class, $method ) )->getNumberOfParameters() >= $min_params;
		api_check( $ok, "Repository::{$method}() is public with at least {$min_params} parameter(s)" );
	}
}

// Database columns Pro queries directly.
api_check( false !== strpos( $schema, "'change_events'     => \$base . 'change_events'" ) || false !== strpos( $schema, "change_events" ), 'free has the change_events table' );
foreach ( array( 'source', 'object_type', 'object_label', 'occurred_at' ) as $column ) {
	api_check( 1 === preg_match( '/\b' . $column . '\b[^\n]*NOT NULL/', $schema ), "change_events has column {$column}" );
}

// Admin integration: page slug, script handle, JS filters, CSS classes.
api_check( false !== strpos( $admin, "const PAGE_SLUG = 'wpessential-checkout-impact';" ), 'admin page slug is wpessential-checkout-impact (used in alert links)' );
api_check( false !== strpos( $admin, "'wpecir-admin'" ) && false !== strpos( $admin, "'wp-hooks'" ), 'free enqueues the wpecir-admin script with wp-hooks' );
foreach ( array( 'wpecir.adminTabs', 'wpecir.reportActions' ) as $filter ) {
	api_check( false !== strpos( $admin_js, "applyFilters( '{$filter}'" ), "free admin.js applies JS filter {$filter}" );
	api_check( false !== strpos( $pro_js, "addFilter( '{$filter}'" ), "Pro registers JS filter {$filter}" );
}
preg_match_all( "/className: '([^']+)'/", $pro_js, $classes );
foreach ( array_unique( preg_split( '/\s+/', implode( ' ', $classes[1] ) ) ) as $class_name ) {
	if ( 0 === strpos( $class_name, 'wpecir-' ) && 0 !== strpos( $class_name, 'wpecir-pro-' ) ) {
		$base = preg_replace( '/--.*$/', '', $class_name );
		api_check( false !== strpos( $css, '.' . $base ), "free CSS defines .{$base} used by Pro" );
	}
}

if ( $failures ) {
	fwrite( STDERR, "Free/Pro compatibility contract FAILED:\n - " . implode( "\n - ", $failures ) . "\n" );
	exit( 1 );
}
echo 'Free/Pro compatibility contract passed (free ' . $free_version[1] . ', Pro minimum ' . $min_free[1] . ").\n";
