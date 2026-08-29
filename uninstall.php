<?php
/**
 * Uninstall handler for Sovexxa plugin.
 *
 * IMPORTANT: This file is executed only when the plugin is uninstalled via WordPress.
 * By default we do NOT remove user data. If the site admin chooses to remove data,
 * they can remove it manually or we can implement a secure option to allow data deletion.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// For safety, do not remove data automatically. Uncomment and adjust carefully if you want to remove all tables and options.
// global $wpdb;
// $prefix = $wpdb->prefix;
// $tables = [
//     "{$prefix}sovexxa_societies",
//     "{$prefix}sovexxa_wings",
//     "{$prefix}sovexxa_floors",
//     "{$prefix}sovexxa_flats",
//     "{$prefix}sovexxa_members",
//     "{$prefix}sovexxa_parking",
//     "{$prefix}sovexxa_maintenance",
//     "{$prefix}sovexxa_receipts",
//     "{$prefix}sovexxa_notices",
//     "{$prefix}sovexxa_complaints",
//     "{$prefix}sovexxa_visitors",
//     "{$prefix}sovexxa_expenses",
//     "{$prefix}sovexxa_staff",
//     "{$prefix}sovexxa_committee_members",
//     "{$prefix}sovexxa_audit_log",
//     "{$prefix}sovexxa_bulk_jobs",
// ];
// foreach ( $tables as $t ) {
//     $wpdb->query( "DROP TABLE IF EXISTS $t" );
// }
// delete_option( 'sovexxa_db_version' );
// delete_option( 'sovexxa_admin_notification_email' );
