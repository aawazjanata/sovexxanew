<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receipt generation: create HTML, optional PDF (Dompdf), email and WhatsApp.
 */
class Receipt {

	private $wpdb;
	private $payments_table;
	private $invoices_table;
	private $items_table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->payments_table = $wpdb->prefix . 'sovexxa_payments';
		$this->invoices_table = $wpdb->prefix . 'sovexxa_invoices';
		$this->items_table    = $wpdb->prefix . 'sovexxa_invoice_items';
	}

	/**
	 * Generate and send receipt for a payment object or id.
	 * $payment param can be object (DB row) or integer id.
	 * Returns receipt file path on success or false.
	 */
	public function generate_and_send_for_payment( $payment ) {
		$payment_obj = null;
		if ( is_int( $payment ) || ctype_digit( (string)$payment ) ) {
			$payment_obj = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->payments_table} WHERE id = %d", intval( $payment ) ) );
			if ( ! $payment_obj ) {
				return false;
			}
		} elseif ( is_object( $payment ) ) {
			$payment_obj = $payment;
		} else {
			return false;
		}

		// Gather invoice and items if present
		$invoice = null;
		$items = [];
		if ( ! empty( $payment_obj->invoice_id ) ) {
			$invoice = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->invoices_table} WHERE id = %d", intval( $payment_obj->invoice_id ) ), ARRAY_A );
			if ( $invoice ) {
				$items = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT description, quantity, unit_price, line_total FROM {$this->items_table} WHERE invoice_id = %d", intval( $invoice['id'] ) ), ARRAY_A );
			}
		}

		// Build data for template
		$receipt_data = [
			'payment_id' => intval( $payment_obj->id ),
			'payment_amount' => floatval( $payment_obj->amount ),
			'payment_method' => $payment_obj->payment_method ?? '',
			'transaction_ref' => $payment_obj->transaction_ref ?? '',
			'paid_by' => intval( $payment_obj->paid_by ),
			'paid_at' => $payment_obj->created_at ?? current_time( 'mysql' ),
			'invoice' => $invoice,
			'items' => $items,
		];

		// Render HTML via template
		$html = $this->render_receipt_html( $receipt_data );

		// Try to render PDF
		$binary = null;
		if ( class_exists( '\\Dompdf\\Dompdf' ) ) {
			// use Dompdf directly
			$binary = \Sovexxa\DomPDF_Integration::html_to_pdf( $html );
		} else {
			// try vendor/autoload if present
			$vendor = SOVEXXA_PLUGIN_DIR . 'vendor/autoload.php';
			if ( file_exists( $vendor ) ) {
				require_once $vendor;
				if ( class_exists( '\\Dompdf\\Dompdf' ) ) {
					$binary = \Sovexxa\DomPDF_Integration::html_to_pdf( $html );
				}
			}
		}

		// Save file to uploads
		$upload = wp_upload_dir();
		$dir = trailingslashit( $upload['basedir'] ) . 'sovexxa_receipts';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$basename = 'receipt-' . $payment_obj->id . '-' . time();
		$filename_pdf = $dir . '/' . $basename . '.pdf';
		$filename_html = $dir . '/' . $basename . '.html';

		if ( $binary !== false && $binary !== null ) {
			file_put_contents( $filename_pdf, $binary );
			$attachment = $filename_pdf;
		} else {
			// Save HTML fallback
			file_put_contents( $filename_html, $html );
			$attachment = $filename_html;
		}

		// Send email to payer (if user exists) and admin
		$to = '';
		$user = null;
		if ( ! empty( $payment_obj->paid_by ) ) {
			$user = get_userdata( intval( $payment_obj->paid_by ) );
			if ( $user && ! empty( $user->user_email ) ) {
				$to = $user->user_email;
			}
		}
		$admin_email = get_option( 'sovexxa_admin_notification_email', get_option( 'admin_email' ) );

		$subject = sprintf( '[%s] Payment Receipt #%d', get_bloginfo( 'name' ), intval( $payment_obj->id ) );
		$body = "Namaskar,\n\nThank you for your payment.\n\nPayment ID: " . intval( $payment_obj->id ) . "\nAmount: " . number_format_i18n( $payment_obj->amount, 2 ) . "\n\nPlease find the receipt attached.\n\nRegards,\n" . get_bloginfo( 'name' );

		$headers = [];
		$attachments = [ $attachment ];

		// Send to payer
		if ( $to ) {
			wp_mail( $to, $subject, $body, $headers, $attachments );
		}
		// Send to admin
		if ( is_email( $admin_email ) ) {
			wp_mail( $admin_email, $subject, $body, $headers, $attachments );
		}

		// Optionally send WhatsApp message: we will send a click-to-chat link via email body or use Business API
		$whatsapp_phone = '';
		if ( $user ) {
			$whatsapp_phone = get_user_meta( $user->ID, 'contact_phone_international', true );
		}
		if ( ! empty( $whatsapp_phone ) ) {
			$msg = "Your payment (ID: " . intval( $payment_obj->id ) . ") of " . number_format_i18n( $payment_obj->amount, 2 ) . " was received. Receipt: " . ( $attachment ? trailingslashit( $upload['baseurl'] ) . 'sovexxa_receipts/' . basename( $attachment ) : '' );
			// Try Business API send (returns true/false)
			$sent = WhatsApp::send_business_message( $whatsapp_phone, $msg );
			// If adapter not configured, do nothing; admin email contains attachment/download link
		}

		return $attachment;
	}

	/**
	 * Render receipt HTML using template file.
	 */
	public function render_receipt_html( $data ) {
		ob_start();
		$invoice = $data['invoice'] ?? null;
		$items   = $data['items'] ?? [];
		$payment_amount = $data['payment_amount'] ?? 0;
		$payment_id = $data['payment_id'] ?? 0;
		$paid_at = $data['paid_at'] ?? current_time( 'mysql' );
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="utf-8">
			<title>Receipt #<?php echo esc_html( $payment_id ); ?></title>
			<style>
				body { font-family: Arial, sans-serif; color:#222; }
				.container { max-width:720px; margin:0 auto; padding:20px; }
				.tbl { width:100%; border-collapse: collapse; margin-top:20px; }
				.tbl th, .tbl td { border:1px solid #ddd; padding:8px; }
				.total { text-align:right; font-weight:bold; }
			</style>
		</head>
		<body>
			<div class="container">
				<h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - Payment Receipt</h2>
				<p><strong>Receipt ID:</strong> <?php echo esc_html( $payment_id ); ?></p>
				<p><strong>Date:</strong> <?php echo esc_html( $paid_at ); ?></p>

				<?php if ( $invoice ) : ?>
					<h3>Invoice: <?php echo esc_html( $invoice['invoice_number'] ?? '' ); ?></h3>
				<?php endif; ?>

				<table class="tbl">
					<thead>
						<tr><th>#</th><th>Description</th><th>Qty</th><th>Unit</th><th>Line</th></tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $items ) ) : $i = 1; foreach ( $items as $it ) : ?>
							<tr>
								<td><?php echo $i; ?></td>
								<td><?php echo esc_html( $it['description'] ?? '' ); ?></td>
								<td><?php echo esc_html( $it['quantity'] ?? '' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $it['unit_price'] ?? 0, 2 ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $it['line_total'] ?? 0, 2 ) ); ?></td>
							</tr>
						<?php $i++; endforeach; else : ?>
							<tr><td colspan="5">Payment for invoice or service. Amount paid shown below.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>

				<p class="total">Amount Paid: <?php echo esc_html( number_format_i18n( $payment_amount, 2 ) ); ?></p>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}