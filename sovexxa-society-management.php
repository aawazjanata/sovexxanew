<?php
/**
 * Plugin Name: Sovexxa Society Management System
 * Plugin URI:  https://example.com/sovexxa
 * Description: Multi‑society Society ERP/Management System for WordPress.
 * Version:     0.1.1
 * Author:      Sovexxa
 * Text Domain: sovexxa
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constants
define( 'SOVEXXA_PLUGIN_FILE', __FILE__ );
define( 'SOVEXXA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOVEXXA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SOVEXXA_DB_VERSION', '2026-08-29-002' ); // bumped for schema/feature additions

// Core includes
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-database.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-activator.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-plugin.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-user-mapping.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-audit-log.php';

// New feature modules
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-security.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-settings.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-flats.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-members.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-maintenance.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-payment.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-receipt.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-reports.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once SOVEXXA_PLUGIN_DIR . 'includes/class-whatsapp.php';

// Activation / Deactivation hooks
register_activation_hook( __FILE__, [ 'Sovexxa\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Sovexxa\\Deactivator', 'deactivate' ] );

// Initialize plugin
add_action( 'plugins_loaded', function() {
	// Run DB migrations
	$db = new Sovexxa\Database();
	$db->maybe_upgrade();

	// Initialize modules (each registers its own hooks)
	new Sovexxa\Plugin();
	new Sovexxa\User_Mapping();
	new Sovexxa\Audit_Log();
	new Sovexxa\Security();
	new Sovexxa\Settings();
	new Sovexxa\Flats();
	new Sovexxa\Members();
	new Sovexxa\Maintenance();
	new Sovexxa\Payment();
	new Sovexxa\Receipt();
	new Sovexxa\Reports();
	new Sovexxa\Shortcodes();
	new Sovexxa\Rest_API();
	new Sovexxa\WhatsApp();
} );

