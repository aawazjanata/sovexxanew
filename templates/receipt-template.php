<?php
// Optional standalone receipt template if you prefer separate file rendering.
// Variables expected: $payment, $invoice (array), $items (array)
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!doctype html><html><head><meta charset="utf-8"><title>Receipt</title></head><body>
	<div class="container">
		<h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - Receipt</h2>
		<p><strong>Receipt ID:</strong> <?php echo esc_html( $payment->id ); ?></p>
		<p><strong>Amount:</strong> <?php echo esc_html( number_format_i18n( $payment->amount, 2 ) ); ?></p>
		<!-- Add invoice/items display if present -->
	</div>
</body></html>