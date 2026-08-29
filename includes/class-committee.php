<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Committee members CRUD
 */
class Committee {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_committee_members';

		add_action( 'init', [ $this, 'maybe_create_table' ] );

		add_action( 'wp_ajax_sovexxa_create_committee', [ $this, 'ajax_create' ] );
		add_action( 'wp_ajax_sovexxa_update_committee', [ $this, 'ajax_update' ] );
		add_action( 'wp_ajax_sovexxa_delete_committee', [ $this, 'ajax_delete' ] );
		add_action( 'wp_ajax_sovexxa_list_committee',   [ $this, 'ajax_list' ] );
	}

	public function maybe_create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(191) NOT NULL,
			position VARCHAR(191) DEFAULT NULL,
			mobile VARCHAR(30) DEFAULT NULL,
			email VARCHAR(191) DEFAULT NULL,
			photo VARCHAR(255) DEFAULT NULL,
			start_date DATE DEFAULT NULL,
			end_date DATE DEFAULT NULL,
			status TINYINT DEFAULT 1,
			display_order INT DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY society_id (society_id)
		) $charset_collate;";
		dbDelta( $sql );
	}

	public function ajax_create() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_committee' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$in = Security::esc_array( $_POST );
		$sid = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$name = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : '';
		if ( ! $sid || empty( $name ) ) {
			wp_send_json_error( [ 'message' => 'society_id and name required' ] );
		}
		if ( ! Security::can_access_society( $sid ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Access denied' ] );
		}
		$this->wpdb->insert( $this->table, [
			'society_id' => $sid,
			'name' => $name,
			'position' => isset( $in['position'] ) ? sanitize_text_field( $in['position'] ) : null,
			'mobile' => isset( $in['mobile'] ) ? sanitize_text_field( $in['mobile'] ) : null,
			'email' => isset( $in['email'] ) ? sanitize_email( $in['email'] ) : null,
			'start_date' => isset( $in['start_date'] ) ? sanitize_text_field( $in['start_date'] ) : null,
			'end_date' => isset( $in['end_date'] ) ? sanitize_text_field( $in['end_date'] ) : null,
			'created_at' => current_time( 'mysql' ),
		], [ '%d','%s','%s','%s','%s','%s','%s','%s' ] );
		wp_send_json_success( [ 'id' => $this->wpdb->insert_id ] );
	}

	public function ajax_update() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_committee' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$in = Security::esc_array( $_POST );
		$id = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
		if ( ! $id ) { wp_send_json_error( [ 'message' => 'id required' ] ); }
		$data = [];
		$formats = [];
		if ( isset( $in['name'] ) ) { $data['name'] = sanitize_text_field( $in['name'] ); $formats[] = '%s'; }
		if ( isset( $in['position'] ) ) { $data['position'] = sanitize_text_field( $in['position'] ); $formats[] = '%s'; }
		if ( empty( $data ) ) { wp_send_json_error( [ 'message' => 'No fields to update' ] ); }
		$updated = $this->wpdb->update( $this->table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
		if ( $updated === false ) wp_send_json_error( [ 'message' => 'Update failed' ] );
		wp_send_json_success( [ 'updated' => $updated ] );
	}

	public function ajax_delete() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_committee' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) wp_send_json_error( [ 'message' => 'id required' ] );
		$deleted = $this->wpdb->update( $this->table, [ 'status' => 0 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
		if ( $deleted === false ) wp_send_json_error( [ 'message' => 'Delete failed' ] );
		wp_send_json_success( [ 'deleted' => $deleted ] );
	}

	public function ajax_list() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		$sid = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		if ( ! $sid ) wp_send_json_error( [ 'message' => 'society_id required' ] );
		if ( ! Security::can_access_society( $sid ) && ! current_user_can( 'sovexxa_manage_all' ) ) wp_send_json_error( [ 'message' => 'Access denied' ] );
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id,name,position,mobile,email,start_date,end_date FROM {$this->table} WHERE society_id = %d AND status = 1 ORDER BY display_order ASC, id DESC", $sid ), ARRAY_A );
		wp_send_json_success( $rows );
	}
}