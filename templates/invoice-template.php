<?php
// Basic HTML invoice template. Variables expected: $invoice (array with keys), $items (array)
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice <?php echo esc_html( $invoice['invoice_number'] ?? '' ); ?></title>
<style>
	body { font-family: Arial, sans-serif; color:#222; }
	.container { max-width: 800px; margin:0 auto; padding:20px; }
	.header { display:flex; justify-content:space-between; align-items:center; }
	.tbl { width:100%; border-collapse: collapse; margin-top:20px; }
	.tbl th, .tbl td { border:1px solid #ddd; padding:8px; }
	.total { text-align:right; font-weight:bold; }
</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<div>
				<h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
				<p><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
			</div>
			<div>
				<h3>Invoice</h3>
				<p><strong>No:</strong> <?php echo esc_html( $invoice['invoice_number'] ); ?></p>
				<p><strong>Issue:</strong> <?php echo esc_html( $invoice['issue_date'] ); ?></p>
				<p><strong>Due:</strong> <?php echo esc_html( $invoice['due_date'] ); ?></p>
			</div>
		</div>

		<table class="tbl">
			<thead>
				<tr><th>#</th><th><?php esc_html_e( 'Description', 'sovexxa' ); ?></th><th><?php esc_html_e( 'Quantity', 'sovexxa' ); ?></th><th><?php esc_html_e( 'Unit', 'sovexxa' ); ?></th><th><?php esc_html_e( 'Line Total', 'sovexxa' ); ?></th></tr>
			</thead>
			<tbody>
				<?php $i = 1; foreach ( $items as $it ) : ?>
					<tr>
						<td><?php echo $i; ?></td>
						<td><?php echo esc_html( $it['description'] ); ?></td>
						<td><?php echo esc_html( $it['quantity'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $it['unit_price'], 2 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $it['line_total'], 2 ) ); ?></td>
					</tr>
				<?php $i++; endforeach; ?>
			</tbody>
		</table>

		<p class="total">Subtotal: <?php echo esc_html( number_format_i18n( $invoice['subtotal'], 2 ) ); ?></p>
		<p class="total">Tax: <?php echo esc_html( number_format_i18n( $invoice['tax'], 2 ) ); ?></p>
		<p class="total">Total: <?php echo esc_html( number_format_i18n( $invoice['total'], 2 ) ); ?></p>
	</div>
</body>
</html>