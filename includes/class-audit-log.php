<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit Log admin renderer
 */
class Audit_Log {

	private $wpdb;
	private $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'sovexxa_audit_log';
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'sovexxa_manage_all' ) && ! current_user_can( 'sovexxa_manage_society' ) ) {
			wp_die( esc_html__( 'Access Denied', 'sovexxa' ) );
		}

		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 30;
		$offset   = ( $page - 1 ) * $per_page;

		$where  = [];
		$params = [];

		if ( current_user_can( 'sovexxa_manage_society' ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
			$my = Security::get_current_user_society_id();
			if ( $my ) {
				$where[]  = 'society_id = %d';
				$params[] = $my;
			}
		} else {
			if ( isset( $_GET['society_id'] ) && $_GET['society_id'] !== '' ) {
				$where[]  = 'society_id = %d';
				$params[] = absint( $_GET['society_id'] );
			}
		}

		if ( isset( $_GET['user_id'] ) && $_GET['user_id'] !== '' ) {
			$where[]  = 'user_id = %d';
			$params[] = absint( $_GET['user_id'] );
		}

		if ( isset( $_GET['action_filter'] ) && $_GET['action_filter'] !== '' ) {
			$where[]  = 'action = %s';
			$params[] = sanitize_text_field( wp_unslash( $_GET['action_filter'] ) );
		}

		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}

		$sql        = "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params[]   = $per_page;
		$params[]   = $offset;
		$prepared   = $this->wpdb->prepare( $sql, $params );
		$rows       = $this->wpdb->get_results( $prepared, ARRAY_A );
		$total      = $this->wpdb->get_var( "SELECT FOUND_ROWS()" );
		$total_page = (int) ceil( $total / $per_page );

		echo '<div class="wrap"><h1>' . esc_html__( 'Sovexxa Audit Log', 'sovexxa' ) . '</h1>';

		echo '<form method="get" class="sovexxa-audit-filters">';
		echo '<input type="hidden" name="page" value="sovexxa_audit_log" />';
		echo '<label>' . esc_html__( 'Action', 'sovexxa' ) . ': <input type="text" name="action_filter" value="' . esc_attr( $_GET['action_filter'] ?? '' ) . '"></label> ';
		echo '<label>' . esc_html__( 'User ID', 'sovexxa' ) . ': <input type="number" name="user_id" value="' . esc_attr( $_GET['user_id'] ?? '' ) . '"></label> ';
		if ( current_user_can( 'sovexxa_manage_all' ) ) {
			echo '<label>' . esc_html__( 'Society ID', 'sovexxa' ) . ': <input type="number" name="society_id" value="' . esc_attr( $_GET['society_id'] ?? '' ) . '"></label> ';
		}
		echo '<button class="button">' . esc_html__( 'Filter', 'sovexxa' ) . '</button>';
		echo ' <a class="button" href="' . esc_url( remove_query_arg( [ 'action_filter', 'user_id', 'society_id', 'paged' ] ) ) . '">' . esc_html__( 'Reset', 'sovexxa' ) . '</a>';
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Timestamp', 'sovexxa' ) . '</th><th>' . esc_html__( 'User' ) . '</th><th>' . esc_html__( 'Society ID' ) . '</th><th>' . esc_html__( 'Action' ) . '</th><th>' . esc_html__( 'Entity' ) . '</th><th>' . esc_html__( 'Entity ID' ) . '</th><th>' . esc_html__( 'IP' ) . '</th><th>' . esc_html__( 'Undo' ) . '</th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No entries found.', 'sovexxa' ) . '</td></tr>';
		} else {
			foreach ( $rows as $r ) {
				$user_display = $r['user_id'] ? ( $r['user_id'] . ' (' . esc_html( get_the_author_meta( 'display_name', $r['user_id'] ) ) . ')' ) : '';
				echo '<tr>';
				echo '<td>' . esc_html( $r['created_at'] ) . '</td>';
				echo '<td>' . esc_html( $user_display ) . '</td>';
				echo '<td>' . esc_html( $r['society_id'] ) . '</td>';
				echo '<td>' . esc_html( $r['action'] ) . '</td>';
				echo '<td>' . esc_html( $r['entity'] ) . '</td>';
				echo '<td>' . esc_html( $r['entity_id'] ) . '</td>';
				echo '<td>' . esc_html( $r['ip'] ) . '</td>';
				$undoable = in_array( $r['action'], [ 'mapped_society_admin', 'unmapped_society_admin', 'mapped_resident_flat', 'bulk_mapped_resident_flat' ], true );
				if ( $undoable ) {
					echo '<td><button class="button sovexxa-audit-undo" data-auditid="' . esc_attr( $r['id'] ) . '">' . esc_html__( 'Undo', 'sovexxa' ) . '</button></td>';
				} else {
					echo '<td>' . esc_html__( '-', 'sovexxa' ) . '</td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		if ( $total_page > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			$current = $page;
			$page_links = paginate_links( [
				'base'     => add_query_arg( 'paged', '%#%' ),
				'format'   => '',
				'prev_text'=> '&laquo;',
				'next_text'=> '&raquo;',
				'total'    => $total_page,
				'current'  => $current,
			] );
			echo $page_links;
			echo '</div></div>';
		}

		echo '</div>';
	}
}