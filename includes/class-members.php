<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Members management: basic CRUD + AJAX
 */
class Members {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_members';

		add_action( 'wp_ajax_sovexxa_create_member', [ $this, 'ajax_create_member' ] );
		add_action( 'wp_ajax_sovexxa_update_member', [ $this, 'ajax_update_member' ] );
		add_action( 'wp_ajax_sovexxa_delete_member', [ $this, 'ajax_delete_member' ] );
		add_action( 'wp_ajax_sovexxa_list_members', [ $this, 'ajax_list_members' ] );
	}

	public function ajax_create_member() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_members' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$in = Security::esc_array( $_POST );
		$society_id = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$flat_id    = isset( $in['flat_id'] ) ? absint( $in['flat_id'] ) : 0;
		$full_name  = isset( $in['full_name'] ) ? sanitize_text_field( $in['full_name'] ) : '';
		if ( ! $society_id || ! $flat_id || $full_name === '' ) {
			wp_send_json_error( [ 'message' => 'society_id, flat_id and full_name required' ] );
		}
		if ( ! Security::can_access_society( $society_id ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Access denied for society' ] );
		}
		$ok = $this->wpdb->insert( $this->table, [
			'society_id' => $society_id,
			'flat_id' => $flat_id,
			'user_id' => isset( $in['user_id'] ) ? absint( $in['user_id'] ) : null,
			'full_name' => $full_name,
			'mobile' => isset( $in['mobile'] ) ? sanitize_text_field( $in['mobile'] ) : null,
			'email' => isset( $in['email'] ) ? sanitize_email( $in['email'] ) : null,
			'relation' => isset( $in['relation'] ) ? sanitize_text_field( $in['relation'] ) : null,
			'is_primary' => isset( $in['is_primary'] ) ? ( $in['is_primary'] ? 1 : 0 ) : 0,
			'created_at' => current_time( 'mysql' ),
			'status' => 1,
		], [ '%d','%d','%d','%s','%s','%s','%d','%s','%d' ] );
		if ( ! $ok ) {
			wp_send_json_error( [ 'message' => 'Insert failed' ] );
		}
		wp_send_json_success( [ 'member_id' => $this->wpdb->insert_id ] );
	}

	public function ajax_update_member() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_members' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$in = Security::esc_array( $_POST );
		$id = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Member ID required' ] );
		}
		$data = [];
		$formats = [];
		if ( isset( $in['full_name'] ) ) { $data['full_name'] = sanitize_text_field( $in['full_name'] ); $formats[] = '%s'; }
		if ( isset( $in['mobile'] ) ) { $data['mobile'] = sanitize_text_field( $in['mobile'] ); $formats[] = '%s'; }
		if ( isset( $in['email'] ) ) { $data['email'] = sanitize_email( $in['email'] ); $formats[] = '%s'; }
		if ( empty( $data ) ) {
			wp_send_json_error( [ 'message' => 'No fields to update' ] );
		}
		$updated = $this->wpdb->update( $this->table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
		if ( $updated === false ) {
			wp_send_json_error( [ 'message' => 'Update failed' ] );
		}
		wp_send_json_success( [ 'updated' => $updated ] );
	}

	public function ajax_delete_member() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_members' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Member ID required' ] );
		}
		$deleted = $this->wpdb->update( $this->table, [ 'status' => 0 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
		if ( $deleted === false ) {
			wp_send_json_error( [ 'message' => 'Delete failed' ] );
		}
		wp_send_json_success( [ 'deleted' => $deleted ] );
	}

	public function ajax_list_members() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_society' ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$flat_id = isset( $_POST['flat_id'] ) ? absint( $_POST['flat_id'] ) : 0;
		if ( ! $flat_id ) {
			wp_send_json_error( [ 'message' => 'Flat ID required' ] );
		}
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id, full_name, mobile, email, is_primary FROM {$this->table} WHERE flat_id = %d AND status = 1 ORDER BY is_primary DESC, full_name ASC", $flat_id ), ARRAY_A );
		wp_send_json_success( $rows );
	}
}