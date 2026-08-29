<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic reports skeleton. Exposes AJAX endpoints for simple reports.
 */
class Reports {

	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		add_action( 'wp_ajax_sovexxa_report_collection', [ $this, 'ajax_report_collection' ] );
		add_action( 'wp_ajax_sovexxa_report_outstanding', [ $this, 'ajax_report_outstanding' ] );
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}

	public function register_menu() {
		add_submenu_page(
			'sovexxa_dashboard',
			__( 'Reports', 'sovexxa' ),
			__( 'Reports', 'sovexxa' ),
			'sovexxa_view_reports',
			'sovexxa_reports',
			[ $this, 'render_reports_page' ]
		);
	}

	public function render_reports_page() {
		if ( ! current_user_can( 'sovexxa_view_reports' ) ) {
			wp_die( esc_html__( 'Access denied', 'sovexxa' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Sovexxa Reports' ) . '</h1>';
		echo '<p>' . esc_html__( 'Use AJAX endpoints to fetch report data.', 'sovexxa' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Collection report (example)
	 * POST: society_id, period
	 */
	public function ajax_report_collection() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_view_reports' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$society_id = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : '';
		// Example: sum payments table for period (if invoice/period related)
		// This is a placeholder query; adapt to actual schema (payments and invoices)
		$total = $this->wpdb->get_var( $this->wpdb->prepare( "
			SELECT COALESCE(SUM(amount),0) FROM {$this->wpdb->prefix}sovexxa_payments WHERE society_id = %d
		", $society_id ) );
		wp_send_json_success( [ 'collected' => floatval( $total ) ] );
	}

	public function ajax_report_outstanding() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_view_reports' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$society_id = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		// Placeholder: count unpaid maintenance invoices
		$total = $this->wpdb->get_var( $this->wpdb->prepare( "
			SELECT COALESCE(SUM(amount),0) FROM {$this->wpdb->prefix}sovexxa_maintenance WHERE society_id = %d AND status = 'unpaid'
		", $society_id ) );
		wp_send_json_success( [ 'outstanding' => floatval( $total ) ] );
	}
}