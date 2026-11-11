<?php
/**
 * Pro add-on contracts: CSV safety and recipient parsing (pure PHP doubles).
 *
 * @package WPEssential\CheckoutImpactRegressionPro
 */

define( 'ABSPATH', __DIR__ . '/' );
function sanitize_email( $email ) {
	return preg_replace( '/[^a-z0-9@._+-]/i', '', (string) $email );
}
function is_email( $email ) {
	return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
}
function sanitize_title( $title ) {
	return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', $title ), '-' ) );
}
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}

$pro = dirname( __DIR__ ) . '/includes/';
require $pro . 'Alerts.php';
require $pro . 'Export.php';

use WPEssential\CheckoutImpactRegressionPro\Alerts;
use WPEssential\CheckoutImpactRegressionPro\Export;

function pro_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

pro_check( array( 'a@example.test', 'b@example.test' ) === Alerts::parse_recipients( 'a@example.test, nope, a@example.test,b@example.test' ), 'recipients are validated and de-duplicated' );
pro_check( 5 === count( Alerts::parse_recipients( 'a1@x.test,a2@x.test,a3@x.test,a4@x.test,a5@x.test,a6@x.test' ) ), 'recipients are capped at five' );
foreach ( array( '=1+1', '+1', '-1', '@SUM(A1)' ) as $formula ) {
	pro_check( 0 === strpos( Export::csv_cell( $formula ), "\"'" ), 'formula-like cell is neutralised: ' . $formula );
}
pro_check( '"a ""b"""' === Export::csv_cell( 'a "b"' ), 'quotes are escaped' );

$scenario = array( 'id' => 3, 'title' => 'DE, T-shirt' );
$reports  = array(
	array(
		'id'          => 9,
		'created_at'  => '2026-11-11 10:00:00',
		'baseline_id' => 2,
		'changes'     => array(
			'shipping_cost'   => array( 'state' => 'changed', 'baseline' => array( 'currency_code' => 'EUR', 'amount' => '7' ), 'current' => array( 'currency_code' => 'EUR', 'amount' => '12' ) ),
			'shipping_method' => array( 'state' => 'missing', 'baseline' => 'flat_rate:1' ),
		),
	),
);
$csv = Export::to_csv( $scenario, $reports );
$lines = explode( "\r\n", trim( $csv ) );
pro_check( 3 === count( $lines ), 'one header plus one row per field' );
pro_check( '"9","2026-11-11 10:00:00","DE, T-shirt","2","shipping_cost","EUR 7","EUR 12","changed"' === $lines[1], 'money values are flattened with currency' );
pro_check( '"9","2026-11-11 10:00:00","DE, T-shirt","2","shipping_method","flat_rate:1","","missing"' === $lines[2], 'missing value exports empty After' );
$json = json_decode( Export::to_json( $scenario, $reports ), true );
pro_check( 3 === $json['scenario']['id'] && 9 === $json['reports'][0]['id'], 'JSON export keeps scenario and report identity' );

echo "Pro alert and export contracts passed (pure PHP doubles).\n";
