<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Parking allocation and log (skeleton)
 */
class Parking {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_parking';

		add_action( 'init', [ $this, 'maybe_create_table' ] );

		add_action( 'wp_ajax_sovexxa_allocate_parking', [ $this, 'ajax_allocate' ] );
		add_action( 'wp_ajax_sovexxa_release_parking',  [ $this, 'ajax_release' ] );
		add_action( 'wp_ajax_sovexxa_list_parking',     [ $this, 'ajax_list' ] );
	}

	public function maybe_create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED DEFAULT NULL,
			flat_id BIGINT UNSIGNED DEFAULT NULL,
			slot_number VARCHAR(50) DEFAULT NULL,
			vehicle_number VARCHAR(50) DEFAULT NULL,
			allocated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			released_at DATETIME DEFAULT NULL,
			created_by BIGINT UNSIGNED DEFAULT NULL,
			status TINYINT DEFAULT 1,
			PRIMARY KEY (id),
			KEY society_id (society_id),
			KEY flat_id (flat_id)
		) $charset_collate;";
		dbDelta( $sql );
	}

	public function ajax_allocate() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_parking' ) ) wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		$in = Security::esc_array( $_POST );
		$sid = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$flat = isset( $in['flat_id'] ) ? absint( $in['flat_id'] ) : 0;
		$slot = isset( $in['slot_number'] ) ? sanitize_text_field( $in['slot_number'] ) : '';
		if ( ! $flat || ! $slot ) wp_send_json_error( [ 'message' => 'flat_id and slot_number required' ] );
		$this->wpdb->insert( $this->table, [
			'society_id' => $sid ?: null,
			'flat_id' => $flat,
			'slot_number' => $slot,
			'vehicle_number' => isset( $in['vehicle_number'] ) ? sanitize_text_field( $in['vehicle_number'] ) : null,
			'allocated_at' => current_time( 'mysql' ),
			'created_by' => get_current_user_id(),
			'status' => 1,
		], [ '%d','%d','%s','%s','%s','%d','%d' ] );
		wp_send_json_success( [ 'id' => $this->wpdb->insert_id ] );
	}

	public function ajax_release() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_parking' ) ) wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) wp_send_json_error( [ 'message' => 'id required' ] );
		$updated = $this->wpdb->update( $this->table, [ 'released_at' => current_time( 'mysql' ), 'status' => 0 ], [ 'id' => $id ], [ '%s','%d' ], [ '%d' ] );
		if ( $updated === false ) wp_send_json_error( [ 'message' => 'Release failed' ] );
		wp_send_json_success( [ 'released' => $updated ] );
	}

	public function ajax_list() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		$sid = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id, flat_id, slot_number, vehicle_number, allocated_at, released_at, status FROM {$this->table} WHERE society_id = %d ORDER BY allocated_at DESC LIMIT 200", $sid ), ARRAY_A );
		wp_send_json_success( $rows );
	}
}