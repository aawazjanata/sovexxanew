<?php
// ... existing file header ...
// Replace or update the admin_assets() method with the following:

public function admin_assets() {
	wp_enqueue_style( 'sovexxa-admin-css', SOVEXXA_PLUGIN_URL . 'admin/css/admin.css', [], '1.0' );
	wp_enqueue_script( 'papaparse', 'https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js', [], '5.3.2', true );
	wp_enqueue_script( 'sovexxa-admin-js', SOVEXXA_PLUGIN_URL . 'admin/js/admin.js', [ 'jquery', 'papaparse' ], '1.0', true );

	// Enqueue invoices admin script (used on invoices admin view)
	wp_enqueue_script( 'sovexxa-invoices-admin-js', SOVEXXA_PLUGIN_URL . 'admin/js/invoices-admin.js', [ 'jquery' ], '1.0', true );

	wp_localize_script( 'sovexxa-admin-js', 'sovexxa_admin', [
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'mapping_nonce' => wp_create_nonce( 'sovexxa_mapping_nonce' ),
	] );
}