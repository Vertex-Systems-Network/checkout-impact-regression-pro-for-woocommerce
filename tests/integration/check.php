<?php
/**
 * Runtime integration check for the free plugin + Pro inside WordPress
 * Playground. Throws on the first failure so the blueprint run fails.
 *
 * @package WPEssential\CheckoutImpactRegressionPro
 */

$result_file = '/integration/result.txt';
@unlink( $result_file );
register_shutdown_function(
	static function () use ( $result_file ) {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) && ! is_file( $result_file ) ) {
			file_put_contents( $result_file, 'FATAL ' . $error['message'] . "\n" );
		}
	}
);
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$results = array();
$check   = static function ( $condition, $message ) use ( &$results, $result_file ) {
	if ( ! $condition ) {
		$results[] = 'FAIL ' . $message;
		file_put_contents( $result_file, implode( "\n", $results ) . "\n" );
		throw new RuntimeException( 'Integration check failed: ' . $message );
	}
	$results[] = 'PASS ' . $message;
};

wp_set_current_user( 1 );

$check( is_plugin_active( 'woocommerce/woocommerce.php' ), 'WooCommerce is active' );
$check( is_plugin_active( 'checkout-impact-regression-for-woocommerce/checkout-impact-regression-for-woocommerce.php' ), 'free plugin is active' );
$check( is_plugin_active( 'checkout-impact-regression-pro-for-woocommerce/checkout-impact-regression-pro-for-woocommerce.php' ), 'Pro plugin is active' );
$check( did_action( 'wpecir_loaded' ) > 0 && defined( 'WPECIR_VERSION' ) && defined( 'WPECIR_PRO_VERSION' ), 'free loaded before Pro booted' );
$check( true === apply_filters( 'wpecir_pro_active', false ), 'Pro registered itself with the free plugin' );
$check( version_compare( WPECIR_VERSION, WPECIR_PRO_MIN_FREE_VERSION, '>=' ), 'free version satisfies Pro minimum' );

do_action( 'rest_api_init' );
$routes = rest_get_server()->get_routes();
foreach ( array( '/wpecir/v1/scenarios', '/wpecir/v1/settings', '/wpecir-pro/v1/alerts', '/wpecir-pro/v1/scenarios/(?P<id>\d+)/export' ) as $route ) {
	$check( isset( $routes[ $route ] ), "REST route {$route} registered" );
}

$request = new WP_REST_Request( 'POST', '/wpecir/v1/scenarios' );
$request->set_body_params(
	array(
		'title'       => 'Integration DE ' . wp_rand(),
		'input'       => array( 'market' => array( 'country' => 'DE' ), 'cart' => array( 'items' => array( array( 'product_id' => 1, 'quantity' => 1 ) ) ), 'currency' => 'EUR' ),
		'expectation' => array( 'shipping_cost' => '4.90' ),
	)
);
$response = rest_do_request( $request );
$check( 200 === $response->get_status(), 'free REST creates a scenario' );
$scenario_id = (int) $response->get_data()['id'];

$observed = static function ( $cost ) {
	return array(
		'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:1' ),
		'shipping.cost'   => array( 'state' => 'observed', 'value' => $cost ),
		'coupon.code'     => array( 'state' => 'missing' ),
		'coupon.discount' => array( 'state' => 'missing' ),
		'payment.methods' => array( 'state' => 'observed', 'value' => 'bacs' ),
	);
};
$save = new WP_REST_Request( 'POST', '/wpecir/v1/comparisons' );
$save->set_body_params( array( 'scenario_id' => $scenario_id, 'before' => $observed( '4.90' ), 'after' => $observed( '12.50' ) ) );
$saved = rest_do_request( $save );
$check( 200 === $saved->get_status() && ! empty( $saved->get_data()['report']['id'] ), 'free REST saves a comparison report' );
$report_id = (int) $saved->get_data()['report']['id'];

$export = new WP_REST_Request( 'GET', '/wpecir-pro/v1/scenarios/' . $scenario_id . '/export' );
$export->set_param( 'format', 'csv' );
$export_response = rest_do_request( $export );
$csv             = 200 === $export_response->get_status() ? (string) $export_response->get_data()['content'] : '';
$check( 1 === (int) $export_response->get_data()['count'] && 0 === strpos( $csv, '"report_id"' ), 'Pro export returns the saved report' );
$check( false !== strpos( $csv, '"' . $report_id . '",' ) && false !== strpos( $csv, '"shipping_cost","EUR 4.9","EUR 12.5","changed"' ), 'exported CSV contains the report\'s before/after values and result' );

\WPEssential\CheckoutImpactRegressionPro\Alerts::update( true, 'ops@example.test, qa@example.test' );
delete_option( \WPEssential\CheckoutImpactRegressionPro\Alerts::QUEUE_OPTION );
$secret_title = 'COD secret ' . wp_rand();
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => $secret_title ) );
$queue = get_option( \WPEssential\CheckoutImpactRegressionPro\Alerts::QUEUE_OPTION );
$check( is_array( $queue ) && count( $queue ) >= 1, 'a free change event reaches the Pro alert queue' );
$mail = null;
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mail ) {
		$mail = $atts;
		return true; // Capture instead of sending.
	},
	10,
	2
);
$check( true === \WPEssential\CheckoutImpactRegressionPro\Alerts::send_digest(), 'Pro sends the digest for free events' );
$check( is_array( $mail ) && array( 'ops@example.test', 'qa@example.test' ) === array_values( (array) $mail['to'] ), 'digest goes to exactly the configured recipients' );
$check( false !== strpos( (string) $mail['message'], 'woocommerce_cod_settings' ) && false !== strpos( (string) $mail['message'], 'page=wpessential-checkout-impact' ), 'digest names the changed setting and links to the free screen' );
$check( false === strpos( (string) $mail['message'], $secret_title ), 'digest does not include setting values' );
$check( ! get_option( \WPEssential\CheckoutImpactRegressionPro\Alerts::QUEUE_OPTION ), 'sent events are removed from the queue' );

wp_set_current_user( 0 );
$check( in_array( rest_do_request( new WP_REST_Request( 'GET', '/wpecir-pro/v1/alerts' ) )->get_status(), array( 401, 403 ), true ), 'Pro REST is closed to anonymous users' );

// Pro must not run without the free plugin.
wp_set_current_user( 1 );
$pro_file  = 'checkout-impact-regression-pro-for-woocommerce/checkout-impact-regression-pro-for-woocommerce.php';
$free_file = 'checkout-impact-regression-for-woocommerce/checkout-impact-regression-for-woocommerce.php';
WP_Plugin_Dependencies::initialize();
$check( WP_Plugin_Dependencies::has_active_dependents( $free_file ), 'WordPress lists Pro as an active dependent of the free plugin (free cannot be deactivated from the Plugins screen)' );
deactivate_plugins( array( $pro_file, $free_file ), true );
$attempt = activate_plugin( $pro_file );
$check( is_wp_error( $attempt ) && ! is_plugin_active( $pro_file ), 'Pro cannot be activated without the free plugin (' . ( is_wp_error( $attempt ) ? $attempt->get_error_code() : 'activated' ) . ')' );
activate_plugin( $free_file );
$check( ! is_wp_error( activate_plugin( $pro_file ) ) && is_plugin_active( $pro_file ), 'Pro activates once the free plugin is active' );

file_put_contents( $result_file, implode( "\n", $results ) . "\nINTEGRATION OK\n" );
