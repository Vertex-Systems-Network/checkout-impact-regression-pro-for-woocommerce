<?php
/**
 * Email alerts when a plugin update or tracked setting change is recorded.
 *
 * @package WPEssential\CheckoutImpactRegressionPro
 */

namespace WPEssential\CheckoutImpactRegressionPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batches change events and sends one digest email per window.
 */
final class Alerts {
	const OPTION       = 'wpecir_pro_alerts';
	const QUEUE_OPTION = 'wpecir_pro_alert_queue';
	const HOOK         = 'wpecir_pro_send_alert_digest';
	const DELAY        = 900; // 15 minutes: an update run often records several events.
	const MAX_QUEUE    = 50;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'wpecir_change_event_recorded', array( __CLASS__, 'queue' ), 10, 3 );
		add_action( self::HOOK, array( __CLASS__, 'send_digest' ) );
	}

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'enabled'    => ! empty( $saved['enabled'] ),
			'recipients' => isset( $saved['recipients'] ) ? (string) $saved['recipients'] : (string) get_option( 'admin_email' ),
		);
	}

	/**
	 * Validate and store settings.
	 *
	 * @param mixed $enabled    Whether alerts are on.
	 * @param mixed $recipients Comma-separated email addresses.
	 * @return array|\WP_Error
	 */
	public static function update( $enabled, $recipients ) {
		$list = self::parse_recipients( (string) $recipients );
		if ( ! is_bool( $enabled ) ) {
			return new \WP_Error( 'wpecir_pro_invalid', __( 'Choose whether alerts are on or off.', 'checkout-impact-regression-pro' ), array( 'status' => 400 ) );
		}
		if ( $enabled && ! $list ) {
			return new \WP_Error( 'wpecir_pro_invalid_recipients', __( 'Enter at least one valid email address.', 'checkout-impact-regression-pro' ), array( 'status' => 400 ) );
		}
		update_option( self::OPTION, array( 'enabled' => $enabled, 'recipients' => implode( ', ', $list ) ), false );
		return self::settings();
	}

	/**
	 * Parse up to 5 valid addresses.
	 *
	 * @param string $raw Raw input.
	 * @return string[]
	 */
	public static function parse_recipients( $raw ) {
		$out = array();
		foreach ( explode( ',', $raw ) as $address ) {
			$address = sanitize_email( trim( $address ) );
			if ( $address && is_email( $address ) && ! in_array( $address, $out, true ) ) {
				$out[] = $address;
			}
		}
		return array_slice( $out, 0, 5 );
	}

	/**
	 * Add an event to the digest queue and schedule a send.
	 *
	 * @param int    $event_id    Event identifier.
	 * @param string $source      Event source.
	 * @param string $object_type Object type.
	 * @return void
	 */
	public static function queue( $event_id, $source, $object_type ) {
		$settings = self::settings();
		if ( ! $settings['enabled'] ) {
			return;
		}
		$queue   = get_option( self::QUEUE_OPTION, array() );
		$queue   = is_array( $queue ) ? $queue : array();
		$queue[] = absint( $event_id );
		update_option( self::QUEUE_OPTION, array_slice( array_values( array_unique( $queue ) ), -self::MAX_QUEUE ), false );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + self::DELAY, self::HOOK );
		}
	}

	/**
	 * Send one digest for all queued events.
	 *
	 * @return bool Whether an email was sent.
	 */
	public static function send_digest() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		delete_option( self::QUEUE_OPTION );
		$settings = self::settings();
		$to       = self::parse_recipients( $settings['recipients'] );
		if ( ! $settings['enabled'] || ! $to || ! is_array( $queue ) || ! $queue ) {
			return false;
		}

		global $wpdb;
		$ids    = array_map( 'absint', $queue );
		$table  = $wpdb->prefix . 'wpecir_change_events';
		$holder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders are generated from the ID count.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT source, object_type, object_label, occurred_at FROM %i WHERE id IN ({$holder}) ORDER BY occurred_at ASC", array_merge( array( $table ), $ids ) ), ARRAY_A );
		if ( ! $rows ) {
			return false;
		}

		$lines = array();
		foreach ( $rows as $row ) {
			$what    = '' !== $row['object_label'] ? $row['object_label'] : $row['object_type'];
			$kind    = 'plugin_update' === $row['source'] ? __( 'Plugin updated', 'checkout-impact-regression-pro' ) : __( 'Setting changed', 'checkout-impact-regression-pro' );
			$lines[] = '- ' . $kind . ': ' . $what . ' (' . $row['occurred_at'] . ' UTC)';
		}
		$link = admin_url( 'admin.php?page=wpessential-checkout-impact#compare' );
		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] Checkout-related change recorded — compare your scenarios', 'checkout-impact-regression-pro' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$body    = __( 'Checkout Impact recorded these changes on your store:', 'checkout-impact-regression-pro' ) . "\n\n" . implode( "\n", $lines ) . "\n\n" .
			__( 'Open checkout for your saved scenarios, record what you see and compare it with the approved baseline:', 'checkout-impact-regression-pro' ) . "\n" . $link . "\n";

		return (bool) wp_mail( $to, $subject, $body );
	}

	/**
	 * Clear scheduled digests on deactivation.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
