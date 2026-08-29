<?php
/**
 * Sovexxa Plugin Main Class
 *
 * @package Sovexxa
 */

namespace Sovexxa;

/**
 * Main Plugin Class
 */
class Plugin {

	/**
	 * Initialize the plugin
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
	}

	/**
	 * Enqueue admin assets
	 */
	public function admin_assets() {
		wp_enqueue_style(
			'sovexxa-admin-css',
			SOVEXXA_PLUGIN_URL . 'admin/css/admin.css',
			[],
			'1.0'
		);

		wp_enqueue_script(
			'papaparse',
			'https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js',
			[],
			'5.3.2',
			true
		);

		wp_enqueue_script(
			'sovexxa-admin-js',
			SOVEXXA_PLUGIN_URL . 'admin/js/admin.js',
			[ 'jquery', 'papaparse' ],
			'1.0',
			true
		);

		wp_enqueue_script(
			'sovexxa-invoices-admin-js',
			SOVEXXA_PLUGIN_URL . 'admin/js/invoices-admin.js',
			[ 'jquery' ],
			'1.0',
			true
		);

		wp_localize_script(
			'sovexxa-admin-js',
			'sovexxa_admin',
			[
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'mapping_nonce' => wp_create_nonce( 'sovexxa_mapping_nonce' ),
			]
		);
	}

	/**
	 * Register admin menu pages
	 */
	public function admin_menu() {
		// Add your admin menu code here
	}

}
