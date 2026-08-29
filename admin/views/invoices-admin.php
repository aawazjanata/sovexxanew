<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
global $wpdb;
$inv_table = $wpdb->prefix . 'sovexxa_invoices';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Invoices', 'sovexxa' ); ?></h1>

	<h2><?php esc_html_e( 'Create Invoice', 'sovexxa' ); ?></h2>
	<form id="sovexxa-create-invoice-form">
		<table class="form-table">
			<tr>
				<th><label><?php esc_html_e( 'Society ID', 'sovexxa' ); ?></label></th>
				<td><input type="number" id="sovexxa-invoice-society" name="society_id" /></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Flat ID', 'sovexxa' ); ?></label></th>
				<td><input type="number" id="sovexxa-invoice-flat" name="flat_id" /></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Items (JSON array)', 'sovexxa' ); ?></label></th>
				<td><textarea id="sovexxa-invoice-items" rows="4" cols="60">[{"description":"Maintenance","quantity":1,"unit_price":1000}]</textarea></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Tax', 'sovexxa' ); ?></label></th>
				<td><input type="number" id="sovexxa-invoice-tax" name="tax" step="0.01" value="0" /></td>
			</tr>
		</table>
		<p><button class="button button-primary" id="sovexxa-create-invoice-btn"><?php esc_html_e( 'Create Invoice' ); ?></button></p>
	</form>

	<hr/>

	<h2><?php esc_html_e( 'Recent Invoices', 'sovexxa' ); ?></h2>
	<div id="sovexxa-invoices-list">
		<p>Loading...</p>
	</div>
</div>