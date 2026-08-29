<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Extended REST API routes for invoices and payments.
 */
class Rest_API_Extended {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( 'sovexxa/v1', '/invoices/(?P<id>\d+)', [
			'methods' => 'GET',
			'callback' => [ $this, 'rest_get_invoice' ],
			'permission_callback' => function( $request ) {
				return current_user_can( 'sovexxa_view_reports' );
			},
		] );
		register_rest_route( 'sovexxa/v1', '/invoices', [
			'methods' => 'GET',
			'callback' => [ $this, 'rest_list_invoices' ],
			'permission_callback' => function() { return current_user_can( 'sovexxa_view_reports' ); },
		] );
		register_rest_route( 'sovexxa/v1', '/payments', [
			'methods' => 'GET',
			'callback' => [ $this, 'rest_list_payments' ],
			'permission_callback' => function() { return current_user_can( 'sovexxa_view_reports' ); },
		] );
	}

	public function rest_get_invoice( \WP_REST_Request $request ) {
		$id = intval( $request->get_param( 'id' ) );
		global $wpdb;
		$inv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sovexxa_invoices WHERE id = %d", $id ), ARRAY_A );
		if ( ! $inv ) {
			return new \WP_Error( 'not_found', 'Invoice not found', [ 'status' => 404 ] );
		}
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sovexxa_invoice_items WHERE invoice_id = %d", $id ), ARRAY_A );
		$inv['items'] = $items;
		return rest_ensure_response( $inv );
	}

	public function rest_list_invoices( \WP_REST_Request $request ) {
		$society_id = $request->get_param( 'society_id' );
		global $wpdb;
		if ( $society_id ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, invoice_number, issue_date, due_date, total, status FROM {$wpdb->prefix}sovexxa_invoices WHERE society_id = %d ORDER BY created_at DESC LIMIT 200", intval( $society_id ) ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( "SELECT id, invoice_number, issue_date, due_date, total, status FROM {$wpdb->prefix}sovexxa_invoices ORDER BY created_at DESC LIMIT 200", ARRAY_A );
		}
		return rest_ensure_response( $rows );
	}

	public function rest_list_payments( \WP_REST_Request $request ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT id, society_id, flat_id, amount, payment_method, created_at FROM {$wpdb->prefix}sovexxa_payments ORDER BY created_at DESC LIMIT 500", ARRAY_A );
		return rest_ensure_response( $rows );
	}
}