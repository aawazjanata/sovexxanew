<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Notices: create, list, update, delete.
 */
class Notices {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_notices';

		add_action( 'init', [ $this, 'maybe_create_table' ] );

		add_action( 'wp_ajax_sovexxa_create_notice', [ $this, 'ajax_create' ] );
		add_action( 'wp_ajax_sovexxa_update_notice', [ $this, 'ajax_update' ] );
		add_action( 'wp_ajax_sovexxa_delete_notice', [ $this, 'ajax_delete' ] );
		add_action( 'wp_ajax_sovexxa_list_notices',  [ $this, 'ajax_list' ] );
	}

	public function maybe_create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED DEFAULT NULL,
			title VARCHAR(191) NOT NULL,
			message LONGTEXT NOT NULL,
			status TINYINT DEFAULT 1,
			created_by BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY society_id (society_id)
		) $charset_collate;";
		dbDelta( $sql );
	}

	public function ajax_create() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_notices' ) ) wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		$in = Security::esc_array( $_POST );
		$sid = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$title = isset( $in['title'] ) ? sanitize_text_field( $in['title'] ) : '';
		$msg = isset( $in['message'] ) ? wp_kses_post( wp_unslash( $in['message'] ) ) : '';
		if ( empty( $title ) || empty( $msg ) ) wp_send_json_error( [ 'message' => 'title and message required' ] );
		if ( $sid && ! Security::can_access_society( $sid ) && ! current_user_can( 'sovexxa_manage_all' ) ) wp_send_json_error( [ 'message' => 'Access denied' ] );
		$this->wpdb->insert( $this->table, [
			'society_id' => $sid ?: null,
			'title' => $title,
			'message' => $msg,
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		], [ '%d','%s','%s','%d','%s' ] );
		wp_send_json_success( [ 'id' => $this->wpdb->insert_id ] );
	}

	public function ajax_update() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_notices' ) ) wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		$in = Security::esc_array( $_POST );
		$id = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
		if ( ! $id ) wp_send_json_error( [ 'message' => 'id required' ] );
		$data = [];
		$formats = [];
		if ( isset( $in['title'] ) ) { $data['title'] = sanitize_text_field( $in['title'] ); $formats[] = '%s'; }
		if ( isset( $in['message'] ) ) { $data['message'] = wp_kses_post( wp_unslash( $in['message'] ) ); $formats[] = '%s'; }
		$updated = $this->wpdb->update( $this->table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
		if ( $updated === false ) wp_send_json_error( [ 'message' => 'Update failed' ] );
		wp_send_json_success( [ 'updated' => $updated ] );
	}

	public function ajax_delete() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_notices' ) ) wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) wp_send_json_error( [ 'message' => 'id required' ] );
		$deleted = $this->wpdb->update( $this->table, [ 'status' => 0 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
		if ( $deleted === false ) wp_send_json_error( [ 'message' => 'Delete failed' ] );
		wp_send_json_success( [ 'deleted' => $deleted ] );
	}

	public function ajax_list() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		$sid = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		$where = '';
		$params = [];
		if ( $sid ) {
			$where = 'WHERE society_id = %d AND status = 1';
			$params[] = $sid;
			if ( ! Security::can_access_society( $sid ) && ! current_user_can( 'sovexxa_manage_all' ) ) wp_send_json_error( [ 'message' => 'Access denied' ] );
		} else {
			$where = 'WHERE status = 1';
		}
		$sql = "SELECT id, society_id, title, message, created_at FROM {$this->table} {$where} ORDER BY created_at DESC LIMIT 100";
		if ( ! empty( $params ) ) $rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $params ), ARRAY_A );
		else $rows = $this->wpdb->get_results( $sql, ARRAY_A );
		wp_send_json_success( $rows );
	}
}