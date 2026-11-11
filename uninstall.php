<?php
/**
 * Remove Pro options. Scenario data belongs to the free plugin and is kept.
 *
 * @package WPEssential\CheckoutImpactRegressionPro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpecir_pro_alerts' );
delete_option( 'wpecir_pro_alert_queue' );
wp_clear_scheduled_hook( 'wpecir_pro_send_alert_digest' );
