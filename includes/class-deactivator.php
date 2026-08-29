<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deactivator {

	public static function deactivate() {
		// Unschedule any scheduled jobs keyed by our hook
		$crons = _get_cron_array();
		if ( ! empty( $crons ) ) {
			foreach ( $crons as $timestamp => $events ) {
				if ( is_array( $events ) ) {
					foreach ( $events as $hook => $args ) {
						if ( $hook === 'sovexxa_process_bulk_job' ) {
							wp_unschedule_event( $timestamp, $hook );
						}
					}
				}
			}
		}
		// Do not remove data on deactivation (safety).
	}
}