<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Basic staff (employees) management
 */
class Staff {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_staff';

		add_action( 'init', [ $this, 'maybe_create_table' ] );

		add_action( 'wp_ajax_sovexxa_create_staff', [ $this, 'ajax_create' ] );
		add_action( 'wp_ajax_sovexxa_update_staff', [ $this, 'ajax_update' ] );
		add_action( 'wp_ajax_sovexxa_list_staff',   [ $this, 'ajax_list' ] );
	}

	public function maybe_create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED DEFAULT NULL,
			name VARCHAR(191) NOT NULL,
			role VARCHAR(191) DEFAULT NULL,
			contact VARCHAR(64) DEFAULT NULL,
			employment_start DATE DEFAULT NULL,
			status TINYINT DEFAULT 1,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY society_id (society_id)
		) $charset_collate;";
		dbDelta( $sql );
	}

	public function ajax_create() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_staff' ) ) wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		$in = Security::esc_array( $_POST );
		$sid = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$name = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : '';
		if ( empty( $name ) ) wp_send_json_error( [ 'message' => 'name required' ] );
		$this->wpdb->insert( $this->table, [
			'society_id' => $sid ?: null,
			'name' => $name,
			'role' => isset( $in['role'] ) ? sanitize_text_field( $in['role'] ) : null,
			'contact' => isset( $in['contact'] ) ? sanitize_text_field( $in['contact'] ) : null,
			'created_at' => current_time( 'mysql' ),
		], [ '%d','%s','%s','%s','%s' ] );
		wp_send_json_success( [ 'id' => $this->wpdb->insert_id ] );
	}

	public function ajax_update() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_staff' ) ) wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		$in = Security::esc_array( $_POST );
		$id = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
		if ( ! $id ) wp_send_json_error( [ 'message' => 'id required' ] );
		$data = [];
		$formats = [];
		if ( isset( $in['name'] ) ) { $data['name'] = sanitize_text_field( $in['name'] ); $formats[] = '%s'; }
		if ( isset( $in['role'] ) ) { $data['role'] = sanitize_text_field( $in['role'] ); $formats[] = '%s'; }
		$updated = $this->wpdb->update( $this->table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
		if ( $updated === false ) wp_send_json_error( [ 'message' => 'Update failed' ] );
		wp_send_json_success( [ 'updated' => $updated ] );
	}

	public function ajax_list() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		$sid = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id,name,role,contact,status FROM {$this->table} WHERE society_id = %d ORDER BY id DESC LIMIT 200", $sid ), ARRAY_A );
		wp_send_json_success( $rows );
	}
}