<?php
/**
 * Pro bootstrap: only runs when the free plugin is loaded.
 *
 * @package WPEssential\CheckoutImpactRegressionPro
 */

namespace WPEssential\CheckoutImpactRegressionPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires Pro features into the free plugin's extension points.
 */
final class Plugin {
	const REST_NAMESPACE = 'wpecir-pro/v1';

	/**
	 * Boot after the free plugin (priority 20) has loaded.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( ! defined( 'WPECIR_VERSION' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'missing_free_notice' ) );
			return;
		}

		add_filter( 'wpecir_pro_active', '__return_true' );
		Alerts::register_hooks();
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Notice when the free plugin is missing.
	 *
	 * @return void
	 */
	public static function missing_free_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Checkout Impact Regression Pro needs the free Checkout Impact Regression for WooCommerce plugin. Install and activate it first.', 'checkout-impact-regression-pro' )
		);
	}

	/**
	 * Same permission as the free plugin's screen.
	 *
	 * @return bool|\WP_Error
	 */
	public static function permissions_check() {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new \WP_Error( 'wpecir_pro_forbidden', __( 'You are not allowed to do this.', 'checkout-impact-regression-pro' ), array( 'status' => 403 ) );
	}

	/**
	 * Register Pro REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/alerts',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static function () {
						return rest_ensure_response( Alerts::settings() );
					},
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => static function ( $request ) {
						$result = Alerts::update( $request->get_param( 'enabled' ), $request->get_param( 'recipients' ) );
						return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
					},
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array(
						'enabled'    => array( 'required' => true, 'type' => 'boolean' ),
						'recipients' => array( 'required' => true, 'type' => 'string' ),
					),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/scenarios/(?P<id>\d+)/export',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Export::class, 'export' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array(
						'id'     => array( 'required' => true, 'sanitize_callback' => 'absint' ),
						'format' => array( 'default' => 'csv', 'enum' => array( 'csv', 'json' ) ),
					),
				),
			)
		);
	}

	/**
	 * Load the Pro UI only on the free plugin's screen.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		if ( ! wp_script_is( 'wpecir-admin', 'enqueued' ) ) {
			return;
		}
		$path = WPECIR_PRO_PATH . 'assets/pro.js';
		wp_enqueue_script( 'wpecir-pro', WPECIR_PRO_URL . 'assets/pro.js', array( 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-hooks' ), is_readable( $path ) ? (string) filemtime( $path ) : WPECIR_PRO_VERSION, true );
		wp_set_script_translations( 'wpecir-pro', 'checkout-impact-regression-pro', WPECIR_PRO_PATH . 'languages' );
		// The free app must render after Pro has registered its filters.
		$free = wp_scripts()->query( 'wpecir-admin' );
		if ( $free && ! in_array( 'wpecir-pro', $free->deps, true ) ) {
			$free->deps[] = 'wpecir-pro';
		}
	}
}
