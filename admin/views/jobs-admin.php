<?php
// Minimal jobs admin view (used by Plugin::render_jobs_admin). This view relies on DB table sovexxa_bulk_jobs.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$page = isset( $_GET['paged'] ) ? max(1, absint( $_GET['paged'] ) ) : 1;
$per_page = 30;
$offset = ( $page - 1 ) * $per_page;

$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sovexxa_bulk_jobs ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sovexxa_bulk_jobs" );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Sovexxa Bulk Jobs', 'sovexxa' ); ?></h1>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Job ID' ); ?></th><th><?php esc_html_e( 'File' ); ?></th><th><?php esc_html_e( 'Status' ); ?></th><th><?php esc_html_e( 'Progress' ); ?></th><th><?php esc_html_e( 'Actions' ); ?></th></tr></thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No jobs found.' ); ?></td></tr>
			<?php else : foreach ( $rows as $r ) : $progress = intval( $r['processed_rows'] ) . ' / ' . intval( $r['total_rows'] ); ?>
				<tr>
					<td><?php echo esc_html( $r['job_id'] ); ?></td>
					<td><?php echo esc_html( $r['file_name'] ); ?></td>
					<td><?php echo esc_html( $r['status'] ); ?></td>
					<td><?php echo esc_html( $progress ); ?></td>
					<td>
						<a class="button" href="<?php echo esc_url( add_query_arg( [ 'page' => 'sovexxa_jobs', 'job_id' => $r['job_id'] ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View' ); ?></a>
						<?php if ( intval( $r['failures_count'] ) > 0 && ! empty( $r['failures_file'] ) && file_exists( $r['failures_file'] ) ) :
							$nonce = wp_create_nonce( 'sovexxa_download_job_' . $r['job_id'] );
							$dl = add_query_arg( [ 'page' => 'sovexxa_jobs', 'job_id' => $r['job_id'], 'download_failures_full' => 1, 'sovexxa_job_nonce' => $nonce ], admin_url( 'admin.php' ) );
						?>
							<a class="button" href="<?php echo esc_url( $dl ); ?>"><?php esc_html_e( 'Download Full Failures' ); ?></a>
						<?php endif; ?>
						<button class="button sovexxa-delete-job" data-jobid="<?php echo esc_attr( $r['job_id'] ); ?>"><?php esc_html_e( 'Delete' ); ?></button>
					</td>
				</tr>
			<?php endforeach; endif; ?>
		</tbody>
	</table>
	<?php
	$total_pages = (int) ceil( $total / $per_page );
	if ( $total_pages > 1 ) {
		echo '<div class="tablenav"><div class="tablenav-pages">';
		$current = $page;
		$page_links = paginate_links( [
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'total' => $total_pages,
			'current' => $current,
		] );
		echo $page_links;
		echo '</div></div>';
	}
	?>
</div>