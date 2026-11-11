<?php
/**
 * CSV and JSON export of saved comparison reports.
 *
 * @package WPEssential\CheckoutImpactRegressionPro
 */

namespace WPEssential\CheckoutImpactRegressionPro;

use WPEssential\CheckoutImpactRegression\Storage\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds export files from the free plugin's report API.
 */
final class Export {
	const PAGE        = 20;
	const MAX_REPORTS = 2000;

	/**
	 * Export one scenario's unexpired reports.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function export( $request ) {
		$repository = new Repository();
		$scenario   = $repository->get_scenario( $request->get_param( 'id' ) );
		if ( is_wp_error( $scenario ) ) {
			return $scenario;
		}
		$reports = array();
		for ( $offset = 0; $offset < self::MAX_REPORTS; $offset += self::PAGE ) {
			$page = $repository->list_manual_comparisons( $scenario['id'], self::PAGE, $offset );
			if ( is_wp_error( $page ) ) {
				return $page;
			}
			$reports = array_merge( $reports, $page );
			if ( count( $page ) < self::PAGE ) {
				break;
			}
		}

		$format   = 'json' === $request->get_param( 'format' ) ? 'json' : 'csv';
		$filename = 'checkout-impact-' . sanitize_title( $scenario['title'] ) . '-' . gmdate( 'Y-m-d' ) . '.' . $format;
		$content  = 'json' === $format ? self::to_json( $scenario, $reports ) : self::to_csv( $scenario, $reports );

		return rest_ensure_response(
			array(
				'filename' => $filename,
				'mime'     => 'json' === $format ? 'application/json' : 'text/csv',
				'content'  => $content,
				'count'    => count( $reports ),
			)
		);
	}

	/**
	 * Rows: one per report field.
	 *
	 * @param array $scenario Scenario.
	 * @param array $reports  Reports.
	 * @return string
	 */
	public static function to_csv( $scenario, $reports ) {
		$rows = array( array( 'report_id', 'created_at_utc', 'scenario', 'baseline_id', 'field', 'before', 'after', 'result' ) );
		foreach ( $reports as $report ) {
			foreach ( (array) $report['changes'] as $field => $change ) {
				$rows[] = array(
					$report['id'],
					$report['created_at'],
					$scenario['title'],
					$report['baseline_id'],
					$field,
					self::value( isset( $change['baseline'] ) ? $change['baseline'] : null ),
					self::value( isset( $change['current'] ) ? $change['current'] : null ),
					$change['state'],
				);
			}
		}
		$lines = array();
		foreach ( $rows as $row ) {
			$lines[] = implode( ',', array_map( array( __CLASS__, 'csv_cell' ), $row ) );
		}
		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Structured export.
	 *
	 * @param array $scenario Scenario.
	 * @param array $reports  Reports.
	 * @return string
	 */
	public static function to_json( $scenario, $reports ) {
		return (string) wp_json_encode(
			array(
				'scenario' => array(
					'id'    => (int) $scenario['id'],
					'title' => $scenario['title'],
				),
				'reports'  => $reports,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	}

	/**
	 * Flatten a stored value for a CSV cell.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function value( $value ) {
		if ( null === $value ) {
			return '';
		}
		if ( is_array( $value ) && isset( $value['amount'] ) ) {
			return ( isset( $value['currency_code'] ) ? $value['currency_code'] . ' ' : '' ) . $value['amount'];
		}
		if ( is_array( $value ) ) {
			return implode( ' ', array_map( 'strval', $value ) );
		}
		return (string) $value;
	}

	/**
	 * Quote a CSV cell and neutralise spreadsheet formulas.
	 *
	 * @param mixed $cell Cell.
	 * @return string
	 */
	public static function csv_cell( $cell ) {
		$cell = (string) $cell;
		if ( '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$cell = "'" . $cell;
		}
		return '"' . str_replace( '"', '""', $cell ) . '"';
	}
}
