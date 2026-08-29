<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Visitors log module
 */
class Visitors {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_visitors';

		add_action( 'init', [ $this, 'maybe_create_table' ] );

		add_action( 'wp_ajax_sovexxa_log_visitor', [ $this, 'ajax_log' ] );
		add_action( 'wp_ajax_sovexxa_list_visitors', [ $this, 'ajax_list' ] );
	}

	public function maybe_create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED DEFAULT NULL,
			visitor_name VARCHAR(191) NOT NULL,
			host_flat_id BIGINT UNSIGNED DEFAULT NULL,
			purpose VARCHAR(191) DEFAULT NULL,
			entered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			left_at DATETIME DEFAULT NULL,
			created_by BIGINT UNSIGNED DEFAULT NULL,
			PRIMARY KEY (id),
			KEY society_id (society_id),
			KEY host_flat_id (host_flat_id)
		) $charset_collate;";
		dbDelta( $sql );
	}

	public function ajax_log() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_all' ) && ! current_user_can( 'sovexxa_manage_society' ) && ! current_user_can( 'sovexxa_manage_visitors' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$in = Security::esc_array( $_POST );
		$sid = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$name = isset( $in['visitor_name'] ) ? sanitize_text_field( $in['visitor_name'] ) : '';
		$flat = isset( $in['host_flat_id'] ) ? absint( $in['host_flat_id'] ) : 0;
		if ( ! $name ) wp_send_json_error( [ 'message' => 'visitor_name required' ] );
		$this->wpdb->insert( $this->table, [
			'society_id' => $sid ?: null,
			'visitor_name' => $name,
			'host_flat_id' => $flat ?: null,
			'purpose' => isset( $in['purpose'] ) ? sanitize_text_field( $in['purpose'] ) : null,
			'entered_at' => current_time( 'mysql' ),
			'created_by' => get_current_user_id(),
		], [ '%d','%s','%d','%s','%s','%d' ] );
		wp_send_json_success( [ 'id' => $this->wpdb->insert_id ] );
	}

	public function ajax_list() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		$sid = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		$sql = "SELECT id, visitor_name, host_flat_id, purpose, entered_at, left_at FROM {$this->table} WHERE society_id = %d ORDER BY entered_at DESC LIMIT 200";
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $sid ), ARRAY_A );
		wp_send_json_success( $rows );
	}
}