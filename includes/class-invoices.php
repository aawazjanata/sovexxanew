<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoices and Invoice Line Items
 *
 * - Creates invoices and line_items tables
 * - Can generate invoices for maintenance items, or arbitrary invoices
 * - Supports scheduling recurring invoice generation via Action Scheduler (if available) or WP Cron fallback
 * - Provides AJAX endpoints for admin UI and a programmatic API
 */
class Invoices {

	private $wpdb;
	private $invoices_table;
	private $items_table;
	private $recurrence_table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->invoices_table    = $wpdb->prefix . 'sovexxa_invoices';
		$this->items_table       = $wpdb->prefix . 'sovexxa_invoice_items';
		$this->recurrence_table  = $wpdb->prefix . 'sovexxa_invoice_recurrence';

		add_action( 'init', [ $this, 'maybe_create_tables' ] );

		// AJAX
		add_action( 'wp_ajax_sovexxa_create_invoice', [ $this, 'ajax_create_invoice' ] );
		add_action( 'wp_ajax_sovexxa_get_invoice', [ $this, 'ajax_get_invoice' ] );
		add_action( 'wp_ajax_sovexxa_list_invoices', [ $this, 'ajax_list_invoices' ] );
		add_action( 'wp_ajax_sovexxa_mark_invoice_paid', [ $this, 'ajax_mark_paid' ] );
		add_action( 'wp_ajax_sovexxa_schedule_recurring', [ $this, 'ajax_schedule_recurring' ] );

		// Worker hook for scheduled recurring invoices
		add_action( 'sovexxa_generate_recurring_invoices', [ $this, 'handle_generate_recurring' ], 10, 1 );
	}

	public function maybe_create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->invoices_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED DEFAULT NULL,
			flat_id BIGINT UNSIGNED DEFAULT NULL,
			invoice_number VARCHAR(100) DEFAULT NULL,
			issue_date DATE DEFAULT NULL,
			due_date DATE DEFAULT NULL,
			subtotal DECIMAL(12,2) DEFAULT 0,
			tax DECIMAL(12,2) DEFAULT 0,
			total DECIMAL(12,2) DEFAULT 0,
			status VARCHAR(32) DEFAULT 'unpaid',
			created_by BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY society_id (society_id),
			KEY flat_id (flat_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		$sql2 = "CREATE TABLE {$this->items_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			invoice_id BIGINT UNSIGNED NOT NULL,
			description VARCHAR(255) DEFAULT NULL,
			quantity INT DEFAULT 1,
			unit_price DECIMAL(12,2) DEFAULT 0,
			line_total DECIMAL(12,2) DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY invoice_id (invoice_id)
		) {$charset_collate};";
		dbDelta( $sql2 );

		$sql3 = "CREATE TABLE {$this->recurrence_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			society_id BIGINT UNSIGNED DEFAULT NULL,
			name VARCHAR(191) DEFAULT NULL,
			cron_spec VARCHAR(191) DEFAULT NULL,
			last_run DATETIME DEFAULT NULL,
			settings LONGTEXT DEFAULT NULL,
			active TINYINT DEFAULT 1,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY society_id (society_id)
		) {$charset_collate};";
		dbDelta( $sql3 );
	}

	/* ---------------------------
	 * Programmatic API
	 * -------------------------- */

	/**
	 * Create an invoice programmatically.
	 * $data: society_id, flat_id, invoice_number, issue_date, due_date, items => [ [description, qty, unit_price], ... ]
	 * Returns invoice ID or WP_Error
	 */
	public function create_invoice( $data ) {
		$society_id = isset( $data['society_id'] ) ? absint( $data['society_id'] ) : 0;
		$flat_id    = isset( $data['flat_id'] ) ? absint( $data['flat_id'] ) : 0;
		$items      = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : [];
		if ( ! $society_id || ! $flat_id || empty( $items ) ) {
			return new \WP_Error( 'invalid_input', 'society_id, flat_id and items are required' );
		}
		// permission check: caller should ensure appropriate capability, this is programmatic
		$sub = 0.0;
		foreach ( $items as $it ) {
			$qty = isset( $it['quantity'] ) ? intval( $it['quantity'] ) : 1;
			$unit = isset( $it['unit_price'] ) ? floatval( $it['unit_price'] ) : 0.0;
			$sub += $qty * $unit;
		}
		$tax = isset( $data['tax'] ) ? floatval( $data['tax'] ) : 0.0;
		$total = $sub + $tax;
		$invoice_number = isset( $data['invoice_number'] ) ? sanitize_text_field( $data['invoice_number'] ) : $this->generate_invoice_number();
		$issue_date = isset( $data['issue_date'] ) ? sanitize_text_field( $data['issue_date'] ) : current_time( 'Y-m-d' );
		$due_date = isset( $data['due_date'] ) ? sanitize_text_field( $data['due_date'] ) : null;

		$ok = $this->wpdb->insert( $this->invoices_table, [
			'society_id' => $society_id,
			'flat_id' => $flat_id,
			'invoice_number' => $invoice_number,
			'issue_date' => $issue_date,
			'due_date' => $due_date,
			'subtotal' => $sub,
			'tax' => $tax,
			'total' => $total,
			'status' => 'unpaid',
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		], [ '%d','%d','%s','%s','%s','%f','%f','%f','%s','%d','%s' ] );

		if ( ! $ok ) {
			return new \WP_Error( 'db_insert_failed', 'Failed to insert invoice' );
		}
		$invoice_id = $this->wpdb->insert_id;

		// Insert items
		foreach ( $items as $it ) {
			$desc = isset( $it['description'] ) ? sanitize_text_field( $it['description'] ) : '';
			$qty = isset( $it['quantity'] ) ? intval( $it['quantity'] ) : 1;
			$unit = isset( $it['unit_price'] ) ? floatval( $it['unit_price'] ) : 0.0;
			$line = $qty * $unit;
			$this->wpdb->insert( $this->items_table, [
				'invoice_id' => $invoice_id,
				'description' => $desc,
				'quantity' => $qty,
				'unit_price' => $unit,
				'line_total' => $line,
				'created_at' => current_time( 'mysql' ),
			], [ '%d','%s','%d','%f','%f','%s' ] );
		}

		// Return ID
		return (int) $invoice_id;
	}

	public function generate_invoice_number() {
		$prefix = get_option( 'sovexxa_invoice_prefix', 'INV' );
		$time = time();
		return $prefix . '-' . $time . '-' . wp_rand( 100, 999 );
	}

	/* ---------------------------
	 * AJAX endpoints
	 * -------------------------- */

	public function ajax_create_invoice() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_bills' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$in = Security::esc_array( $_POST );
		$sid = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$flat = isset( $in['flat_id'] ) ? absint( $in['flat_id'] ) : 0;
		$items_raw = isset( $in['items'] ) ? $in['items'] : null;
		$items = [];
		if ( is_string( $items_raw ) ) {
			$decoded = json_decode( wp_unslash( $items_raw ), true );
			if ( is_array( $decoded ) ) { $items = $decoded; }
		} elseif ( is_array( $items_raw ) ) {
			$items = $items_raw;
		}
		$tax = isset( $in['tax'] ) ? floatval( $in['tax'] ) : 0.0;
		if ( ! $sid || ! $flat || empty( $items ) ) {
			wp_send_json_error( [ 'message' => 'society_id, flat_id and items required' ] );
		}
		$res = $this->create_invoice( [
			'society_id' => $sid,
			'flat_id' => $flat,
			'items' => $items,
			'tax' => $tax,
		] );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( [ 'message' => $res->get_error_message() ] );
		}
		wp_send_json_success( [ 'invoice_id' => $res ] );
	}

	public function ajax_get_invoice() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! ( current_user_can( 'sovexxa_view_reports' ) || current_user_can( 'sovexxa_manage_bills' ) ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
		if ( ! $invoice_id ) wp_send_json_error( [ 'message' => 'invoice_id required' ] );
		$inv = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->invoices_table} WHERE id = %d", $invoice_id ), ARRAY_A );
		if ( ! $inv ) wp_send_json_error( [ 'message' => 'Invoice not found' ] );
		$items = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->items_table} WHERE invoice_id = %d", $invoice_id ), ARRAY_A );
		$inv['items'] = $items;
		wp_send_json_success( $inv );
	}

	public function ajax_list_invoices() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! ( current_user_can( 'sovexxa_view_reports' ) || current_user_can( 'sovexxa_manage_bills' ) ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$sid = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		$flat = isset( $_POST['flat_id'] ) ? absint( $_POST['flat_id'] ) : 0;
		$where = [];
		$params = [];
		if ( $sid ) { $where[] = 'society_id = %d'; $params[] = $sid; }
		if ( $flat ) { $where[] = 'flat_id = %d'; $params[] = $flat; }
		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}
		$sql = "SELECT id, invoice_number, issue_date, due_date, total, status FROM {$this->invoices_table} {$where_sql} ORDER BY created_at DESC LIMIT 200";
		$rows = ! empty( $params ) ? $this->wpdb->get_results( $this->wpdb->prepare( $sql, $params ), ARRAY_A ) : $this->wpdb->get_results( $sql, ARRAY_A );
		wp_send_json_success( $rows );
	}

	public function ajax_mark_paid() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_payments' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
		if ( ! $id ) wp_send_json_error( [ 'message' => 'invoice_id required' ] );
		$updated = $this->wpdb->update( $this->invoices_table, [ 'status' => 'paid' ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
		if ( $updated === false ) wp_send_json_error( [ 'message' => 'Update failed' ] );
		wp_send_json_success( [ 'updated' => $updated ] );
	}

	/* ---------------------------
	 * Recurrence scheduling
	 * -------------------------- */

	/**
	 * Schedule a recurring invoice generator record
	 * POST: society_id, name, cron_spec (e.g., daily/monthly), settings (json)
	 */
	public function ajax_schedule_recurring() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_bills' ) ) wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		$in = Security::esc_array( $_POST );
		$sid = isset( $in['society_id'] ) ? absint( $in['society_id'] ) : 0;
		$name = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : '';
		$cron = isset( $in['cron_spec'] ) ? sanitize_text_field( $in['cron_spec'] ) : '';
		$settings = isset( $in['settings'] ) ? maybe_serialize( $in['settings'] ) : null;
		if ( ! $sid || ! $name || ! $cron ) wp_send_json_error( [ 'message' => 'society_id,name,cron_spec required' ] );
		$this->wpdb->insert( $this->recurrence_table, [
			'society_id' => $sid,
			'name' => $name,
			'cron_spec' => $cron,
			'settings' => $settings,
			'active' => 1,
			'created_at' => current_time( 'mysql' ),
		], [ '%d','%s','%s','%s','%d','%s' ] );
		$id = $this->wpdb->insert_id;
		// schedule initial run in 60s (Action Scheduler if available)
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 60, 'sovexxa_generate_recurring_invoices', [ 'recurrence_id' => $id ], 'sovexxa' );
		} else {
			if ( ! wp_next_scheduled( 'sovexxa_generate_recurring_invoices', [ 'recurrence_id' => $id ] ) ) {
				wp_schedule_single_event( time() + 60, 'sovexxa_generate_recurring_invoices', [ 'recurrence_id' => $id ] );
			}
		}
		wp_send_json_success( [ 'recurrence_id' => $id ] );
	}

	/**
	 * Handler for scheduled recurring invoice generation.
	 * $arg = [ 'recurrence_id' => X ]
	 */
	public function handle_generate_recurring( $arg ) {
		$recurrence_id = 0;
		if ( is_array( $arg ) && isset( $arg['recurrence_id'] ) ) {
			$recurrence_id = absint( $arg['recurrence_id'] );
		} elseif ( is_int( $arg ) ) {
			$recurrence_id = $arg;
		}
		if ( ! $recurrence_id ) {
			return;
		}
		$rec = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->recurrence_table} WHERE id = %d AND active = 1", $recurrence_id ), ARRAY_A );
		if ( ! $rec ) {
			return;
		}
		$settings = $rec['settings'] ? maybe_unserialize( $rec['settings'] ) : [];
		// Example: settings contain amount_per_flat and period; we will create invoices for all flats in society
		$amount = isset( $settings['amount_per_flat'] ) ? floatval( $settings['amount_per_flat'] ) : 0;
		$period = isset( $settings['period'] ) ? sanitize_text_field( $settings['period'] ) : date( 'Y-m' );

		if ( $amount <= 0 ) {
			return;
		}
		$flats = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id FROM {$this->wpdb->prefix}sovexxa_flats WHERE society_id = %d AND status = 1", $rec['society_id'] ), ARRAY_A );
		foreach ( $flats as $f ) {
			$items = [
				[ 'description' => 'Maintenance: ' . $period, 'quantity' => 1, 'unit_price' => $amount ],
			];
			$this->create_invoice( [
				'society_id' => $rec['society_id'],
				'flat_id' => $f['id'],
				'items' => $items,
				'tax' => 0,
			] );
		}
		// Update last_run
		$this->wpdb->update( $this->recurrence_table, [ 'last_run' => current_time( 'mysql' ) ], [ 'id' => $recurrence_id ], [ '%s' ], [ '%d' ] );

		// Reschedule next run according to cron_spec (simple: daily/monthly)
		$cron = $rec['cron_spec'];
		$next = 0;
		if ( $cron === 'daily' ) {
			$next = time() + DAY_IN_SECONDS;
		} elseif ( $cron === 'monthly' ) {
			$next = strtotime( '+1 month' );
		} else {
			// unsupported cron_spec; do not reschedule automatically
			$next = 0;
		}
		if ( $next ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( $next, 'sovexxa_generate_recurring_invoices', [ 'recurrence_id' => $recurrence_id ], 'sovexxa' );
			} else {
				if ( ! wp_next_scheduled( 'sovexxa_generate_recurring_invoices', [ 'recurrence_id' => $recurrence_id ] ) ) {
					wp_schedule_single_event( $next, 'sovexxa_generate_recurring_invoices', [ 'recurrence_id' => $recurrence_id ] );
				}
			}
		}
	}
}