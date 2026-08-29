<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment recording and notification (enhanced).
 */
class Payment {

	private $wpdb;
	private $payments_table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->payments_table = $wpdb->prefix . 'sovexxa_payments';

		add_action( 'init', [ $this, 'maybe_create_table' ] );
		add_action( 'wp_ajax_sovexxa_record_payment', [ $this, 'ajax_record_payment' ] );

		// Listen for programmatic payment recordings too
		add_action( 'sovexxa_payment_recorded', [ $this, 'after_payment_recorded' ], 10, 1 );
	}

	public function maybe_create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$this->payments_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED DEFAULT NULL,
			flat_id BIGINT UNSIGNED DEFAULT NULL,
			invoice_id BIGINT UNSIGNED DEFAULT NULL,
			payment_method VARCHAR(50) DEFAULT NULL,
			amount DECIMAL(12,2) NOT NULL,
			transaction_ref VARCHAR(191) DEFAULT NULL,
			paid_by BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY society_id (society_id),
			KEY flat_id (flat_id),
			KEY invoice_id (invoice_id)
		) $charset_collate;";
		dbDelta( $sql );
	}

	/**
	 * Record a payment (AJAX).
	 * POST: invoice_id, amount, payment_method, transaction_ref, flat_id, society_id
	 */
	public function ajax_record_payment() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_payments' ) && ! current_user_can( 'sovexxa_manage_bills' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$in = Security::esc_array( $_POST );
		$invoice_id = isset( $in['invoice_id'] ) ? absint( $in['invoice_id'] ) : 0;
		$amount = isset( $in['amount'] ) ? floatval( $in['amount'] ) : 0.0;
		$society_id = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$flat_id = isset( $in['flat_id'] ) ? absint( $in['flat_id'] ) : 0;
		if ( $amount <= 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid amount' ] );
		}
		$this->wpdb->insert( $this->payments_table, [
			'society_id' => $society_id,
			'flat_id' => $flat_id,
			'invoice_id' => $invoice_id,
			'payment_method' => isset( $in['payment_method'] ) ? sanitize_text_field( $in['payment_method'] ) : 'manual',
			'amount' => $amount,
			'transaction_ref' => isset( $in['transaction_ref'] ) ? sanitize_text_field( $in['transaction_ref'] ) : null,
			'paid_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		], [ '%d','%d','%d','%s','%f','%s','%d','%s' ] );
		$pid = $this->wpdb->insert_id;

		// Fire action so receipts can be generated or notifications sent
		do_action( 'sovexxa_payment_recorded', $pid );

		wp_send_json_success( [ 'payment_id' => $pid ] );
	}

	/**
	 * After-payment hook handler (ensure it's idempotent if called twice)
	 * $payment_id int
	 */
	public function after_payment_recorded( $payment_id ) {
		$payment = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->payments_table} WHERE id = %d", intval( $payment_id ) ), OBJECT );
		if ( ! $payment ) {
			return;
		}

		// Generate receipt and email/WhatsApp the customer (best-effort)
		try {
			$receipt = new Receipt();
			$receipt_id = $receipt->generate_and_send_for_payment( $payment );

			// Optionally update invoice status if payment covers invoice fully
			if ( ! empty( $payment->invoice_id ) ) {
				// Basic: set invoice to paid (you can extend to check totals)
				$this->wpdb->update( $this->wpdb->prefix . 'sovexxa_invoices', [ 'status' => 'paid' ], [ 'id' => intval( $payment->invoice_id ) ], [ '%s' ], [ '%d' ] );
			}
		} catch ( \Throwable $e ) {
			// Do not throw — just log
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Sovexxa: receipt generation failed for payment ' . intval( $payment_id ) . ' - ' . $e->getMessage() );
			}
		}
	}
}