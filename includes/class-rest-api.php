<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API routes (skeleton)
 */
class Rest_API {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( 'sovexxa/v1', '/flats', [
			'methods' => 'GET',
			'callback' => [ $this, 'rest_list_flats' ],
			'permission_callback' => function( $request ) {
				return current_user_can( 'sovexxa_view_reports' );
			},
		] );
		register_rest_route( 'sovexxa/v1', '/members', [
			'methods' => 'GET',
			'callback' => [ $this, 'rest_list_members' ],
			'permission_callback' => function( $request ) {
				return is_user_logged_in();
			},
		] );
	}

	public function rest_list_flats( \WP_REST_Request $request ) {
		$society_id = $request->get_param( 'society_id' );
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, flat_number, flat_type FROM {$wpdb->prefix}sovexxa_flats WHERE society_id = %d AND status = 1", intval( $society_id ) ), ARRAY_A );
		return rest_ensure_response( $rows );
	}

	public function rest_list_members( \WP_REST_Request $request ) {
		$flat_id = $request->get_param( 'flat_id' );
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, full_name, mobile, email FROM {$wpdb->prefix}sovexxa_members WHERE flat_id = %d AND status = 1", intval( $flat_id ) ), ARRAY_A );
		return rest_ensure_response( $rows );
	}
}