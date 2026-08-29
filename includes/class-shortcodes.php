<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes for resident portal and public views
 */
class Shortcodes {

	public function __construct() {
		add_shortcode( 'sovexxa_portal', [ $this, 'render_portal' ] );
		add_shortcode( 'sovexxa_notices', [ $this, 'render_notices' ] );
		add_shortcode( 'sovexxa_bills', [ $this, 'render_bills' ] );
	}

	public function render_portal( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>Please log in to access the Resident Portal.</p>';
		}
		ob_start();
		$current = wp_get_current_user();
		echo '<div class="sovexxa-portal">';
		echo '<h2>Welcome, ' . esc_html( $current->display_name ) . '</h2>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=sovexxa_jobs' ) ) . '">Portal Home</a></p>';
		echo '</div>';
		return ob_get_clean();
	}

	public function render_notices( $atts ) {
		// minimal: show latest notices from table if exists
		global $wpdb;
		$table = $wpdb->prefix . 'sovexxa_notices';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			return '<p>No notices configured.</p>';
		}
		$rows = $wpdb->get_results( "SELECT id, title, message, created_at FROM {$table} WHERE status = 1 ORDER BY created_at DESC LIMIT 10", ARRAY_A );
		if ( empty( $rows ) ) {
			return '<p>No notices.</p>';
		}
		$html = '<ul class="sovexxa-notices">';
		foreach ( $rows as $r ) {
			$html .= '<li><strong>' . esc_html( $r['title'] ) . '</strong> — <em>' . esc_html( $r['created_at'] ) . '</em><div>' . esc_html( $r['message'] ) . '</div></li>';
		}
		$html .= '</ul>';
		return $html;
	}

	public function render_bills( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>Please log in to view bills.</p>';
		}
		// Minimal: show "not implemented" placeholder
		return '<p>Resident bills view is not yet implemented. Coming soon.</p>';
	}
}