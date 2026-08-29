<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Expenses module for society (skeleton)
 */
class Expenses {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_expenses';

		add_action( 'init', [ $this, 'maybe_create_table' ] );

		add_action( 'wp_ajax_sovexxa_create_expense', [ $this, 'ajax_create' ] );
		add_action( 'wp_ajax_sovexxa_list_expenses',  [ $this, 'ajax_list' ] );
	}

	public function maybe_create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED DEFAULT NULL,
			category VARCHAR(191) DEFAULT NULL,
			description LONGTEXT DEFAULT NULL,
			amount DECIMAL(12,2) NOT NULL,
			incurred_by BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY society_id (society_id)
		) $charset_collate;";
		dbDelta( $sql );
	}

	public function ajax_create() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_bills' ) ) wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		$in = Security::esc_array( $_POST );
		$sid = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$amt = isset( $in['amount'] ) ? floatval( $in['amount'] ) : 0.0;
		if ( $amt <= 0 ) wp_send_json_error( [ 'message' => 'Invalid amount' ] );
		$this->wpdb->insert( $this->table, [
			'society_id' => $sid ?: null,
			'category' => isset( $in['category'] ) ? sanitize_text_field( $in['category'] ) : null,
			'description' => isset( $in['description'] ) ? sanitize_textarea_field( $in['description'] ) : null,
			'amount' => $amt,
			'incurred_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		], [ '%d','%s','%s','%f','%d','%s' ] );
		wp_send_json_success( [ 'id' => $this->wpdb->insert_id ] );
	}

	public function ajax_list() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		$sid = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id, category, description, amount, created_at FROM {$this->table} WHERE society_id = %d ORDER BY created_at DESC LIMIT 500", $sid ), ARRAY_A );
		wp_send_json_success( $rows );
	}
}