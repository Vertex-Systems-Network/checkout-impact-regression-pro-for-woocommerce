<?php
/**
 * Plugin Name:          Checkout Impact Regression Pro for WooCommerce
 * Plugin URI:           https://wpessential.org/plugins/checkout-impact-regression-pro
 * Description:          Adds change alerts by email and CSV/JSON report export to Checkout Impact Regression for WooCommerce.
 * Version:              1.0.0
 * Requires at least:    6.9
 * Requires PHP:         8.2
 * Requires Plugins:     woocommerce, checkout-impact-regression-for-woocommerce
 * Author:               WPEssential
 * Author URI:           https://wpessential.org
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          checkout-impact-regression-pro
 * WC requires at least: 10.8
 * WC tested up to:      11.2
 *
 * @package WPEssential\CheckoutImpactRegressionPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPECIR_PRO_VERSION', '1.0.0' );
define( 'WPECIR_PRO_FILE', __FILE__ );
define( 'WPECIR_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPECIR_PRO_URL', plugin_dir_url( __FILE__ ) );

require_once WPECIR_PRO_PATH . 'includes/Alerts.php';
require_once WPECIR_PRO_PATH . 'includes/Export.php';
require_once WPECIR_PRO_PATH . 'includes/Plugin.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPECIR_PRO_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WPECIR_PRO_FILE, true );
		}
	}
);

register_deactivation_hook( WPECIR_PRO_FILE, array( \WPEssential\CheckoutImpactRegressionPro\Alerts::class, 'unschedule' ) );

add_action( 'plugins_loaded', array( \WPEssential\CheckoutImpactRegressionPro\Plugin::class, 'boot' ), 30 );
