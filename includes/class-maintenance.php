<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintenance billing skeleton: create maintenance bill entries per flat.
 * For brevity this stores invoices in a simple table (sovexxa_maintenance)
 * You may expand with line-items, recurrence, PDF, notifications.
 */
class Maintenance {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_maintenance';

		// create table if doesn't exist (simple)
		add_action( 'init', [ $this, 'maybe_create_table' ] );

		add_action( 'wp_ajax_sovexxa_generate_maintenance', [ $this, 'ajax_generate_maintenance' ] );
		add_action( 'wp_ajax_sovexxa_list_maintenance', [ $this, 'ajax_list_maintenance' ] );
	}

	public function maybe_create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED NOT NULL,
			flat_id BIGINT UNSIGNED NOT NULL,
			period VARCHAR(50) NOT NULL,
			amount DECIMAL(12,2) NOT NULL,
			status VARCHAR(32) DEFAULT 'unpaid',
			generated_by BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY society_id (society_id),
			KEY flat_id (flat_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );
	}

	/**
	 * Generate maintenance bills for a society for a period
	 * POST: society_id, period (e.g., 2026-08), amount_per_flat (optional)
	 */
	public function ajax_generate_maintenance() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_bills' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$in = Security::esc_array( $_POST );
		$society_id = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$period     = isset( $in['period'] ) ? sanitize_text_field( $in['period'] ) : '';
		$amount     = isset( $in['amount_per_flat'] ) ? floatval( $in['amount_per_flat'] ) : 0.0;
		if ( ! $society_id || $period === '' || $amount <= 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid input' ] );
		}
		if ( ! Security::can_access_society( $society_id ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Access denied' ] );
		}
		// fetch flats
		$flats = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id FROM {$this->wpdb->prefix}sovexxa_flats WHERE society_id = %d AND status = 1", $society_id ), ARRAY_A );
		$inserted = 0;
		foreach ( $flats as $f ) {
			$this->wpdb->insert( $this->table, [
				'society_id' => $society_id,
				'flat_id' => $f['id'],
				'period' => $period,
				'amount' => $amount,
				'status' => 'unpaid',
				'generated_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
			], [ '%d', '%d', '%s', '%f', '%s', '%d', '%s' ] );
			$inserted++;
		}
		wp_send_json_success( [ 'inserted' => $inserted ] );
	}

	public function ajax_list_maintenance() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_view_reports' ) && ! current_user_can( 'sovexxa_manage_bills' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$flat_id = isset( $_POST['flat_id'] ) ? absint( $_POST['flat_id'] ) : 0;
		$society_id = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		$where = [];
		$params = [];
		if ( $flat_id ) {
			$where[] = 'flat_id = %d'; $params[] = $flat_id;
		}
		if ( $society_id ) {
			$where[] = 'society_id = %d'; $params[] = $society_id;
		}
		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}
		$sql = "SELECT id, society_id, flat_id, period, amount, status, created_at FROM {$this->table} {$where_sql} ORDER BY created_at DESC LIMIT 500";
		if ( ! empty( $params ) ) {
			$stmt = $this->wpdb->prepare( $sql, $params );
			$rows = $this->wpdb->get_results( $stmt, ARRAY_A );
		} else {
			$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		}
		wp_send_json_success( $rows );
	}
}