<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Complaints / Requests module
 */
class Complaints {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_complaints';

		add_action( 'init', [ $this, 'maybe_create_table' ] );

		add_action( 'wp_ajax_sovexxa_create_complaint', [ $this, 'ajax_create' ] );
		add_action( 'wp_ajax_sovexxa_update_complaint', [ $this, 'ajax_update' ] );
		add_action( 'wp_ajax_sovexxa_list_complaints',  [ $this, 'ajax_list' ] );
	}

	public function maybe_create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED DEFAULT NULL,
			flat_id BIGINT UNSIGNED DEFAULT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			subject VARCHAR(191) NOT NULL,
			message LONGTEXT NOT NULL,
			status VARCHAR(32) DEFAULT 'open',
			assigned_to BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY society_id (society_id),
			KEY flat_id (flat_id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );
	}

	public function ajax_create() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Login required' ] );
		$in = Security::esc_array( $_POST );
		$sid = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$flat = isset( $in['flat_id'] ) ? absint( $in['flat_id'] ) : 0;
		$sub = isset( $in['subject'] ) ? sanitize_text_field( $in['subject'] ) : '';
		$msg = isset( $in['message'] ) ? wp_kses_post( wp_unslash( $in['message'] ) ) : '';
		if ( empty( $sub ) || empty( $msg ) ) wp_send_json_error( [ 'message' => 'subject and message required' ] );
		$this->wpdb->insert( $this->table, [
			'society_id' => $sid ?: null,
			'flat_id' => $flat ?: null,
			'user_id' => get_current_user_id(),
			'subject' => $sub,
			'message' => $msg,
			'status' => 'open',
			'created_at' => current_time( 'mysql' ),
		], [ '%d','%d','%d','%s','%s','%s','%s' ] );
		wp_send_json_success( [ 'id' => $this->wpdb->insert_id ] );
	}

	public function ajax_update() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		$in = Security::esc_array( $_POST );
		$id = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
		$action_user_can = current_user_can( 'sovexxa_manage_complaints' ) || ( get_current_user_id() === $this->wpdb->get_var( $this->wpdb->prepare( "SELECT user_id FROM {$this->table} WHERE id = %d", $id ) ) );
		if ( ! $action_user_can ) wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		$data = [];
		$formats = [];
		if ( isset( $in['status'] ) ) { $data['status'] = sanitize_text_field( $in['status'] ); $formats[] = '%s'; }
		if ( isset( $in['assigned_to'] ) ) { $data['assigned_to'] = absint( $in['assigned_to'] ); $formats[] = '%d'; }
		if ( empty( $data ) ) wp_send_json_error( [ 'message' => 'No fields to update' ] );
		$updated = $this->wpdb->update( $this->table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
		if ( $updated === false ) wp_send_json_error( [ 'message' => 'Update failed' ] );
		wp_send_json_success( [ 'updated' => $updated ] );
	}

	public function ajax_list() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_complaints' ) && ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Login required' ] );
		$sid = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		$where = '';
		$params = [];
		if ( $sid ) {
			$where = 'WHERE society_id = %d';
			$params[] = $sid;
			if ( ! Security::can_access_society( $sid ) && ! current_user_can( 'sovexxa_manage_all' ) ) wp_send_json_error( [ 'message' => 'Access denied' ] );
		}
		$sql = "SELECT id, subject, status, user_id, created_at FROM {$this->table} {$where} ORDER BY created_at DESC LIMIT 200";
		$rows = ! empty( $params ) ? $this->wpdb->get_results( $this->wpdb->prepare( $sql, $params ), ARRAY_A ) : $this->wpdb->get_results( $sql, ARRAY_A );
		wp_send_json_success( $rows );
	}
}