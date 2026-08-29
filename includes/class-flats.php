<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flats management: basic CRUD + AJAX endpoints
 */
class Flats {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_flats';

		add_action( 'wp_ajax_sovexxa_create_flat', [ $this, 'ajax_create_flat' ] );
		add_action( 'wp_ajax_sovexxa_update_flat', [ $this, 'ajax_update_flat' ] );
		add_action( 'wp_ajax_sovexxa_delete_flat', [ $this, 'ajax_delete_flat' ] );
		add_action( 'wp_ajax_sovexxa_list_flats', [ $this, 'ajax_list_flats' ] );
	}

	public function ajax_create_flat() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_flats' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$input = Security::esc_array( $_POST );
		$society_id = isset( $input['society_id'] ) ? absint( $input['society_id'] ) : 0;
		$flat_number = isset( $input['flat_number'] ) ? sanitize_text_field( $input['flat_number'] ) : '';
		if ( ! $society_id || $flat_number === '' ) {
			wp_send_json_error( [ 'message' => 'society_id and flat_number required' ] );
		}
		if ( ! Security::can_access_society( $society_id ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Access denied for society' ] );
		}
		$ok = $this->wpdb->insert( $this->table, [
			'society_id' => $society_id,
			'wing_id' => isset( $input['wing_id'] ) ? absint( $input['wing_id'] ) : null,
			'floor_id' => isset( $input['floor_id'] ) ? absint( $input['floor_id'] ) : null,
			'flat_number' => $flat_number,
			'flat_type' => isset( $input['flat_type'] ) ? sanitize_text_field( $input['flat_type'] ) : null,
			'area' => isset( $input['area'] ) ? sanitize_text_field( $input['area'] ) : null,
			'ownership_status' => isset( $input['ownership_status'] ) ? sanitize_text_field( $input['ownership_status'] ) : null,
			'status' => 1,
			'created_at' => current_time( 'mysql' ),
		], [ '%d','%d','%d','%s','%s','%s','%s','%s' ] );
		if ( ! $ok ) {
			wp_send_json_error( [ 'message' => 'Insert failed' ] );
		}
		wp_send_json_success( [ 'flat_id' => $this->wpdb->insert_id ] );
	}

	public function ajax_update_flat() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_flats' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$input = Security::esc_array( $_POST );
		$id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Flat ID required' ] );
		}
		$data = [];
		$formats = [];
		if ( isset( $input['flat_number'] ) ) { $data['flat_number'] = sanitize_text_field( $input['flat_number'] ); $formats[] = '%s'; }
		if ( isset( $input['flat_type'] ) ) { $data['flat_type'] = sanitize_text_field( $input['flat_type'] ); $formats[] = '%s'; }
		if ( isset( $input['area'] ) ) { $data['area'] = sanitize_text_field( $input['area'] ); $formats[] = '%s'; }
		if ( empty( $data ) ) {
			wp_send_json_error( [ 'message' => 'No fields to update' ] );
		}
		$updated = $this->wpdb->update( $this->table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
		if ( $updated === false ) {
			wp_send_json_error( [ 'message' => 'Update failed' ] );
		}
		wp_send_json_success( [ 'updated' => $updated ] );
	}

	public function ajax_delete_flat() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_flats' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Flat ID required' ] );
		}
		$deleted = $this->wpdb->update( $this->table, [ 'status' => 0 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
		if ( $deleted === false ) {
			wp_send_json_error( [ 'message' => 'Delete failed' ] );
		}
		wp_send_json_success( [ 'deleted' => $deleted ] );
	}

	public function ajax_list_flats() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_society' ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$society_id = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		if ( ! $society_id ) {
			wp_send_json_error( [ 'message' => 'Society ID required' ] );
		}
		if ( ! Security::can_access_society( $society_id ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Access denied' ] );
		}
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id, flat_number, flat_type, area FROM {$this->table} WHERE society_id = %d AND status = 1 ORDER BY flat_number ASC", $society_id ), ARRAY_A );
		wp_send_json_success( $rows );
	}
}