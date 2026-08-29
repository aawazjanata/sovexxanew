<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User mapping admin with background CSV processing, failures file creation, retry and email summary.
 */
class User_Mapping {

	private $wpdb;
	private $flats_table;
	private $members_table;
	private $societies_table;
	private $audit_table;
	private $jobs_table;
	private $upload_subdir = 'sovexxa_bulk';
	private $chunk_size = 100; // rows per run

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->flats_table = $wpdb->prefix . 'sovexxa_flats';
		$this->members_table = $wpdb->prefix . 'sovexxa_members';
		$this->societies_table = $wpdb->prefix . 'sovexxa_societies';
		$this->audit_table = $wpdb->prefix . 'sovexxa_audit_log';
		$this->jobs_table = $wpdb->prefix . 'sovexxa_bulk_jobs';

		// Mapping endpoints
		add_action( 'wp_ajax_sovexxa_search_users', [ $this, 'ajax_search_users' ] );
		add_action( 'wp_ajax_sovexxa_get_flats_by_society', [ $this, 'ajax_get_flats_by_society' ] );
		add_action( 'wp_ajax_sovexxa_get_members_by_flat', [ $this, 'ajax_get_members_by_flat' ] );
		add_action( 'wp_ajax_sovexxa_map_society_admin', [ $this, 'ajax_map_society_admin' ] );
		add_action( 'wp_ajax_sovexxa_unassign_society_admin', [ $this, 'ajax_unassign_society_admin' ] );
		add_action( 'wp_ajax_sovexxa_map_resident_flat', [ $this, 'ajax_map_resident_flat' ] );

		// Background job endpoints
		add_action( 'wp_ajax_sovexxa_upload_bulk_csv', [ $this, 'ajax_upload_bulk_csv' ] );
		add_action( 'wp_ajax_sovexxa_bulk_job_status', [ $this, 'ajax_bulk_job_status' ] );
		add_action( 'wp_ajax_sovexxa_delete_job', [ $this, 'ajax_delete_job' ] );

		// Retry and filtered downloads
		add_action( 'wp_ajax_sovexxa_retry_failed_job', [ $this, 'ajax_retry_failed_job' ] );
		add_action( 'wp_ajax_sovexxa_create_filtered_failures_file', [ $this, 'ajax_create_filtered_failures_file' ] );

		// Undo endpoint
		add_action( 'wp_ajax_sovexxa_audit_undo', [ $this, 'ajax_audit_undo' ] );

		// Worker hook
		add_action( 'sovexxa_process_bulk_job', [ $this, 'process_job_cron' ], 10, 1 );
	}

	/* ---------------------------
	 * DB helpers
	 * -------------------------- */

	private function insert_job( $job ) {
		return ( $this->wpdb->insert(
			$this->jobs_table,
			[
				'job_id'           => $job['job_id'],
				'file_path'        => $job['file_path'],
				'file_name'        => $job['file_name'],
				'mapping'          => maybe_serialize( $job['mapping'] ),
				'status'           => $job['status'],
				'total_rows'       => $job['total_rows'],
				'processed_rows'   => $job['processed_rows'],
				'offset'           => $job['offset'],
				'successes_count'  => $job['successes_count'],
				'failures_count'   => $job['failures_count'],
				'failures_sample'  => maybe_serialize( $job['failures_sample'] ),
				'failures_file'    => isset( $job['failures_file'] ) ? $job['failures_file'] : null,
				'original_header'  => isset( $job['original_header'] ) ? maybe_serialize( $job['original_header'] ) : null,
				'parent_job_id'    => isset( $job['parent_job_id'] ) ? $job['parent_job_id'] : null,
				'created_by'       => $job['created_by'],
				'created_at'       => $job['created_at'],
				'started_at'       => $job['started_at'],
				'finished_at'      => $job['finished_at'],
			],
			[
				'%s','%s','%s','%s','%s','%d','%d','%d','%d','%d','%s','%s','%s','%s','%d','%s','%s'
			]
		) !== false );
	}

	private function update_job( $job_id, $fields ) {
		$formats = [];
		$data    = [];
		foreach ( $fields as $k => $v ) {
			if ( in_array( $k, [ 'mapping', 'failures_sample', 'original_header' ], true ) ) {
				$data[ $k ] = maybe_serialize( $v );
				$formats[] = '%s';
			} elseif ( in_array( $k, [ 'file_path', 'file_name', 'status', 'created_at', 'started_at', 'finished_at', 'failures_file', 'parent_job_id' ], true ) ) {
				$data[ $k ] = $v;
				$formats[] = '%s';
			} else {
				$data[ $k ] = $v;
				$formats[] = '%d';
			}
		}
		if ( empty( $data ) ) {
			return false;
		}
		$where        = [ 'job_id' => $job_id ];
		$where_format = [ '%s' ];
		$updated      = $this->wpdb->update( $this->jobs_table, $data, $where, $formats, $where_format );
		return ( $updated !== false );
	}

	public function get_job( $job_id ) {
		$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->jobs_table} WHERE job_id = %s", $job_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$row['mapping']          = $row['mapping'] ? maybe_unserialize( $row['mapping'] ) : [];
		$row['failures_sample']  = $row['failures_sample'] ? maybe_unserialize( $row['failures_sample'] ) : [];
		$row['original_header']  = $row['original_header'] ? maybe_unserialize( $row['original_header'] ) : [];
		return $row;
	}

	/* ---------------------------
	 * AJAX: Search / Get helpers
	 * -------------------------- */

	public function ajax_search_users() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_all' ) && ! current_user_can( 'sovexxa_manage_society' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$q    = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$role = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
		$args = [
			'number'         => 50,
			'search'         => '*' . $q . '*',
			'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
			'fields'         => [ 'ID', 'display_name', 'user_email', 'user_login' ],
		];
		if ( $role ) {
			$args['role__in'] = [ $role ];
		}
		$users   = get_users( $args );
		$results = [];
		foreach ( $users as $u ) {
			$label     = $u->display_name ?: ( $u->user_email ?: $u->user_login );
			$results[] = [
				'id'    => $u->ID,
				'label' => sprintf( '%s (%s)', $label, $u->user_email ),
			];
		}
		wp_send_json_success( $results );
	}

	public function ajax_get_flats_by_society() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_all' ) && ! current_user_can( 'sovexxa_manage_society' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$society_id = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		if ( ! $society_id ) {
			wp_send_json_error( [ 'message' => 'Society ID required' ] );
		}
		if ( ! current_user_can( 'sovexxa_manage_all' ) && current_user_can( 'sovexxa_manage_society' ) ) {
			if ( ! Security::can_access_society( $society_id ) ) {
				wp_send_json_error( [ 'message' => 'Access denied for this society' ] );
			}
		}
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id, flat_number FROM {$this->flats_table} WHERE society_id = %d AND status = 1 ORDER BY flat_number ASC", $society_id ), ARRAY_A );
		wp_send_json_success( $rows );
	}

	public function ajax_get_members_by_flat() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_all' ) && ! current_user_can( 'sovexxa_manage_society' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$flat_id = isset( $_POST['flat_id'] ) ? absint( $_POST['flat_id'] ) : 0;
		if ( ! $flat_id ) {
			wp_send_json_error( [ 'message' => 'Flat ID required' ] );
		}
		$society_id = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT society_id FROM {$this->flats_table} WHERE id = %d", $flat_id ) );
		if ( ! $society_id ) {
			wp_send_json_error( [ 'message' => 'Flat not found' ] );
		}
		if ( ! current_user_can( 'sovexxa_manage_all' ) && current_user_can( 'sovexxa_manage_society' ) ) {
			if ( ! Security::can_access_society( (int) $society_id ) ) {
				wp_send_json_error( [ 'message' => 'Access denied' ] );
			}
		}
		$members = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id, full_name, mobile FROM {$this->members_table} WHERE flat_id = %d AND status = 1 ORDER BY is_primary DESC, full_name ASC", $flat_id ), ARRAY_A );
		wp_send_json_success( $members );
	}

	/* ---------------------------
	 * Mapping endpoints
	 * -------------------------- */

	public function ajax_map_society_admin() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Only Sovexxa Super Admin can assign Society Admins' ] );
		}
		$user_id    = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$society_id = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		if ( ! $user_id || ! $society_id ) {
			wp_send_json_error( [ 'message' => 'User and Society are required' ] );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( [ 'message' => 'User not found' ] );
		}
		$society_exists = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT id FROM {$this->societies_table} WHERE id = %d", $society_id ) );
		if ( ! $society_exists ) {
			wp_send_json_error( [ 'message' => 'Society not found' ] );
		}
		$u = new \WP_User( $user_id );
		if ( ! in_array( 'sovexxa_society_admin', (array) $u->roles, true ) ) {
			$u->add_role( 'sovexxa_society_admin' );
		}
		update_user_meta( $user_id, 'sovexxa_society_id', $society_id );
		delete_user_meta( $user_id, 'sovexxa_flat_id' );
		delete_user_meta( $user_id, 'sovexxa_member_id' );
		$this->wpdb->insert( $this->audit_table, [
			'user_id'    => get_current_user_id(),
			'society_id' => $society_id,
			'action'     => 'mapped_society_admin',
			'entity'     => 'user',
			'entity_id'  => $user_id,
			'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? esc_html( $_SERVER['REMOTE_ADDR'] ) : '',
			'created_at' => current_time( 'mysql' ),
		], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ] );
		$this->notify_user_assigned_society_admin( $user_id, $society_id );
		wp_send_json_success( [ 'message' => 'User mapped as Society Admin' ] );
	}

	public function ajax_unassign_society_admin() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Only Sovexxa Super Admin can unassign Society Admins' ] );
		}
		$user_ids = [];
		if ( isset( $_POST['user_ids'] ) && is_array( $_POST['user_ids'] ) ) {
			$user_ids = array_map( 'absint', $_POST['user_ids'] );
		} elseif ( isset( $_POST['user_id'] ) ) {
			$user_ids[] = absint( $_POST['user_id'] );
		}
		if ( empty( $user_ids ) ) {
			wp_send_json_error( [ 'message' => 'No user IDs provided' ] );
		}
		$results = [ 'successes' => [], 'failures' => [] ];
		foreach ( $user_ids as $uid ) {
			$user = get_userdata( $uid );
			if ( ! $user ) {
				$results['failures'][] = [ 'user_id' => $uid, 'reason' => 'User not found' ];
				continue;
			}
			$society_id = get_user_meta( $uid, 'sovexxa_society_id', true );
			$society_id = $society_id ? absint( $society_id ) : null;
			$wp_user = new \WP_User( $uid );
			$wp_user->remove_role( 'sovexxa_society_admin' );
			delete_user_meta( $uid, 'sovexxa_society_id' );
			$this->wpdb->insert( $this->audit_table, [
				'user_id'    => get_current_user_id(),
				'society_id' => $society_id,
				'action'     => 'unmapped_society_admin',
				'entity'     => 'user',
				'entity_id'  => $uid,
				'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? esc_html( $_SERVER['REMOTE_ADDR'] ) : '',
				'created_at' => current_time( 'mysql' ),
			], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ] );
			$this->notify_user_unassigned_society_admin( $uid, $society_id );
			$results['successes'][] = [ 'user_id' => $uid ];
		}
		wp_send_json_success( $results );
	}

	public function ajax_map_resident_flat() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		$user_id    = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$society_id = isset( $_POST['society_id'] ) ? absint( $_POST['society_id'] ) : 0;
		$flat_id    = isset( $_POST['flat_id'] ) ? absint( $_POST['flat_id'] ) : 0;
		$member_id  = isset( $_POST['member_id'] ) ? absint( $_POST['member_id'] ) : 0;
		if ( ! $user_id || ! $society_id || ! $flat_id ) {
			wp_send_json_error( [ 'message' => 'User, Society and Flat are required' ] );
		}
		if ( ! current_user_can( 'sovexxa_manage_all' ) ) {
			if ( ! current_user_can( 'sovexxa_manage_society' ) || ! Security::can_access_society( $society_id ) ) {
				wp_send_json_error( [ 'message' => 'Insufficient capability or access to this society' ] );
			}
		}
		$belongs = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT id FROM {$this->flats_table} WHERE id = %d AND society_id = %d", $flat_id, $society_id ) );
		if ( ! $belongs ) {
			wp_send_json_error( [ 'message' => 'Flat does not belong to society' ] );
		}
		if ( $member_id ) {
			$member_ok = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT id FROM {$this->members_table} WHERE id = %d AND flat_id = %d AND society_id = %d", $member_id, $flat_id, $society_id ) );
			if ( ! $member_ok ) {
				wp_send_json_error( [ 'message' => 'Member does not belong to specified flat/society' ] );
			}
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( [ 'message' => 'User not found' ] );
		}
		$wp_user = new \WP_User( $user_id );
		if ( ! in_array( 'sovexxa_flat_resident', (array) $wp_user->roles, true ) ) {
			$wp_user->add_role( 'sovexxa_flat_resident' );
		}
		update_user_meta( $user_id, 'sovexxa_society_id', $society_id );
		update_user_meta( $user_id, 'sovexxa_flat_id', $flat_id );
		if ( $member_id ) {
			update_user_meta( $user_id, 'sovexxa_member_id', $member_id );
		} else {
			delete_user_meta( $user_id, 'sovexxa_member_id' );
		}
		$this->wpdb->insert( $this->audit_table, [
			'user_id'    => get_current_user_id(),
			'society_id' => $society_id,
			'action'     => 'mapped_resident_flat',
			'entity'     => 'user',
			'entity_id'  => $user_id,
			'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? esc_html( $_SERVER['REMOTE_ADDR'] ) : '',
			'created_at' => current_time( 'mysql' ),
		], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ] );
		$this->notify_user_mapped_resident( $user_id, $society_id, $flat_id, $member_id );
		wp_send_json_success( [ 'message' => 'Resident mapped to flat' ] );
	}

	/* ---------------------------
	 * Background job endpoints: upload, status, delete
	 * -------------------------- */

	public function ajax_upload_bulk_csv() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_all' ) && ! current_user_can( 'sovexxa_manage_society' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		if ( empty( $_FILES['csv_file'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( [ 'message' => 'CSV file required' ] );
		}
		$mapping_json = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '';
		$mapping      = [];
		if ( ! empty( $mapping_json ) ) {
			$decoded = json_decode( $mapping_json, true );
			if ( is_array( $decoded ) ) {
				$mapping = $decoded;
			}
		}
		// save file
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . $this->upload_subdir;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$original_name = sanitize_file_name( $_FILES['csv_file']['name'] );
		$uniq          = time() . '-' . wp_rand( 1000, 9999 );
		$target        = $dir . '/' . $uniq . '-' . $original_name;
		if ( ! move_uploaded_file( $_FILES['csv_file']['tmp_name'], $target ) ) {
			wp_send_json_error( [ 'message' => 'Failed to store uploaded file' ] );
		}
		// Read header and count rows
		$original_header = [];
		$total_rows      = 0;
		if ( ( $handle = fopen( $target, 'r' ) ) !== false ) {
			$hdr = fgetcsv( $handle );
			if ( $hdr && is_array( $hdr ) ) {
				$original_header = array_map( 'trim', $hdr );
			}
			while ( ( $buffer = fgetcsv( $handle ) ) !== false ) {
				$total_rows++;
			}
			fclose( $handle );
		}
		$job_id = uniqid( 'sovexxa_job_' );
		$now    = current_time( 'mysql' );
		$job    = [
			'job_id'          => $job_id,
			'file_path'       => $target,
			'file_name'       => $original_name,
			'mapping'         => $mapping,
			'status'          => 'pending',
			'total_rows'      => $total_rows,
			'processed_rows'  => 0,
			'offset'          => 0,
			'successes_count' => 0,
			'failures_count'  => 0,
			'failures_sample' => [],
			'failures_file'   => null,
			'original_header' => $original_header,
			'parent_job_id'   => null,
			'created_by'      => get_current_user_id(),
			'created_at'      => $now,
			'started_at'      => null,
			'finished_at'     => null,
		];
		$ok = $this->insert_job( $job );
		if ( ! $ok ) {
			wp_send_json_error( [ 'message' => 'Failed to create job record' ] );
		}
		// schedule initial run (AS if available or WP Cron fallback)
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 5, 'sovexxa_process_bulk_job', [ 'job_id' => $job_id ], 'sovexxa' );
		} else {
			$bundled = SOVEXXA_PLUGIN_DIR . 'includes/vendor/action-scheduler/autoload.php';
			if ( file_exists( $bundled ) ) {
				require_once $bundled;
				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action( time() + 5, 'sovexxa_process_bulk_job', [ 'job_id' => $job_id ], 'sovexxa' );
				} else {
					if ( ! wp_next_scheduled( 'sovexxa_process_bulk_job', [ $job_id ] ) ) {
						wp_schedule_single_event( time() + 5, 'sovexxa_process_bulk_job', [ $job_id ] );
					}
				}
			} else {
				if ( ! wp_next_scheduled( 'sovexxa_process_bulk_job', [ $job_id ] ) ) {
					wp_schedule_single_event( time() + 5, 'sovexxa_process_bulk_job', [ $job_id ] );
				}
			}
		}
		wp_send_json_success( [ 'job_id' => $job_id, 'message' => 'Job scheduled', 'total_rows' => $total_rows ] );
	}

	public function ajax_bulk_job_status() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		if ( ! $job_id ) {
			wp_send_json_error( [ 'message' => 'Job ID required' ] );
		}
		$job = $this->get_job( $job_id );
		if ( ! $job ) {
			wp_send_json_error( [ 'message' => 'Job not found' ] );
		}
		wp_send_json_success( $job );
	}

	public function ajax_delete_job() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		if ( ! $job_id ) {
			wp_send_json_error( [ 'message' => 'Job ID required' ] );
		}
		$job = $this->get_job( $job_id );
		if ( $job ) {
			if ( ! empty( $job['file_path'] ) && file_exists( $job['file_path'] ) ) {
				@unlink( $job['file_path'] );
			}
			if ( ! empty( $job['failures_file'] ) && file_exists( $job['failures_file'] ) ) {
				@unlink( $job['failures_file'] );
			}
		}
		$deleted = $this->wpdb->delete( $this->jobs_table, [ 'job_id' => $job_id ], [ '%s' ] );
		if ( $deleted === false ) {
			wp_send_json_error( [ 'message' => 'Failed to delete job' ] );
		}
		wp_send_json_success( [ 'message' => 'Job deleted' ] );
	}

	/* ---------------------------
	 * Retry failed job -> create a retry CSV + job
	 * -------------------------- */

	public function ajax_retry_failed_job() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_all' ) && ! current_user_can( 'sovexxa_manage_society' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$job_id        = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$rows_selected = [];
		if ( isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ) {
			$rows_selected = array_map( 'intval', $_POST['rows'] );
		}
		if ( ! $job_id ) {
			wp_send_json_error( [ 'message' => 'Job ID required' ] );
		}
		$job = $this->get_job( $job_id );
		if ( ! $job ) {
			wp_send_json_error( [ 'message' => 'Job not found' ] );
		}
		if ( empty( $job['failures_file'] ) || ! file_exists( $job['failures_file'] ) ) {
			wp_send_json_error( [ 'message' => 'No failures file available to retry' ] );
		}

		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . $this->upload_subdir;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$new_name = 'retry-' . time() . '-' . wp_rand( 1000, 9999 ) . '.csv';
		$new_path = $dir . '/' . $new_name;

		$orig_header = is_array( $job['original_header'] ) ? $job['original_header'] : [];
		if ( empty( $orig_header ) ) {
			if ( file_exists( $job['file_path'] ) ) {
				if ( ( $h = fopen( $job['file_path'], 'r' ) ) !== false ) {
					$hdr = fgetcsv( $h );
					if ( $hdr ) {
						$orig_header = array_map( 'trim', $hdr );
					}
					fclose( $h );
				}
			}
		}

		$fin = fopen( $job['failures_file'], 'r' );
		if ( ! $fin ) {
			wp_send_json_error( [ 'message' => 'Unable to open failures file' ] );
		}
		$f_hdr = fgetcsv( $fin );
		$row_index = 0;

		$fout = fopen( $new_path, 'w' );
		if ( ! $fout ) {
			fclose( $fin );
			wp_send_json_error( [ 'message' => 'Unable to create retry CSV' ] );
		}
		if ( ! empty( $orig_header ) ) {
			fputcsv( $fout, $orig_header );
		} else {
			fputcsv( $fout, [ 'raw_data' ] );
		}
		$written = 0;
		while ( ( $row = fgetcsv( $fin ) ) !== false ) {
			$row_index++;
			$include = empty( $rows_selected ) ? true : in_array( $row_index, $rows_selected, true );
			if ( ! $include ) {
				continue;
			}
			$raw_json = isset( $row[2] ) ? $row[2] : null;
			if ( $raw_json ) {
				$decoded = json_decode( $raw_json, true );
				if ( is_array( $decoded ) ) {
					fputcsv( $fout, $decoded );
				} else {
					fputcsv( $fout, [ $raw_json ] );
				}
			} else {
				fputcsv( $fout, [] );
			}
			$written++;
		}
		fclose( $fin );
		fclose( $fout );

		if ( $written === 0 ) {
			@unlink( $new_path );
			wp_send_json_error( [ 'message' => 'No selected failed rows to retry' ] );
		}

		$new_job_id = uniqid( 'sovexxa_job_' );
		$now        = current_time( 'mysql' );
		$new_job    = [
			'job_id'          => $new_job_id,
			'file_path'       => $new_path,
			'file_name'       => $new_name,
			'mapping'         => $job['mapping'],
			'status'          => 'pending',
			'total_rows'      => $written,
			'processed_rows'  => 0,
			'offset'          => 0,
			'successes_count' => 0,
			'failures_count'  => 0,
			'failures_sample' => [],
			'failures_file'   => null,
			'original_header' => $job['original_header'],
			'parent_job_id'   => $job['job_id'],
			'created_by'      => get_current_user_id(),
			'created_at'      => $now,
			'started_at'      => null,
			'finished_at'     => null,
		];
		$ok = $this->insert_job( $new_job );
		if ( ! $ok ) {
			wp_send_json_error( [ 'message' => 'Failed to create retry job record' ] );
		}
		// schedule retry
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 5, 'sovexxa_process_bulk_job', [ 'job_id' => $new_job_id ], 'sovexxa' );
		} else {
			if ( ! wp_next_scheduled( 'sovexxa_process_bulk_job', [ $new_job_id ] ) ) {
				wp_schedule_single_event( time() + 5, 'sovexxa_process_bulk_job', [ $new_job_id ] );
			}
		}
		$this->wpdb->insert( $this->audit_table, [
			'user_id'    => get_current_user_id(),
			'society_id' => 0,
			'action'     => 'created_retry_job',
			'entity'     => 'job',
			'entity_id'  => 0,
			'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? esc_html( $_SERVER['REMOTE_ADDR'] ) : '',
			'created_at' => current_time( 'mysql' ),
		], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ] );
		wp_send_json_success( [ 'job_id' => $new_job_id, 'message' => 'Retry job created and scheduled' ] );
	}

	/**
	 * Create temporary filtered failures CSV and return URL
	 */
	public function ajax_create_filtered_failures_file() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		if ( ! current_user_can( 'sovexxa_manage_society' ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient capability' ] );
		}
		$job_id        = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$rows_selected = [];
		if ( isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ) {
			$rows_selected = array_map( 'intval', $_POST['rows'] );
		}
		if ( ! $job_id ) {
			wp_send_json_error( [ 'message' => 'Job ID required' ] );
		}
		$job = $this->get_job( $job_id );
		if ( ! $job ) {
			wp_send_json_error( [ 'message' => 'Job not found' ] );
		}
		if ( empty( $job['failures_file'] ) || ! file_exists( $job['failures_file'] ) ) {
			wp_send_json_error( [ 'message' => 'No failures file available' ] );
		}
		$upload    = wp_upload_dir();
		$dir       = trailingslashit( $upload['basedir'] ) . $this->upload_subdir;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$temp_name = 'filtered-' . $job_id . '-' . time() . '-' . wp_rand( 1000, 9999 ) . '.csv';
		$temp_path = $dir . '/' . $temp_name;
		$fin       = fopen( $job['failures_file'], 'r' );
		if ( ! $fin ) {
			wp_send_json_error( [ 'message' => 'Unable to open failures file' ] );
		}
		$f_hdr = fgetcsv( $fin );
		$fout  = fopen( $temp_path, 'w' );
		if ( ! $fout ) {
			fclose( $fin );
			wp_send_json_error( [ 'message' => 'Unable to create filtered file' ] );
		}
		fputcsv( $fout, $f_hdr );
		$line = 0;
		while ( ( $row = fgetcsv( $fin ) ) !== false ) {
			$line++;
			if ( empty( $rows_selected ) || in_array( $line, $rows_selected, true ) ) {
				fputcsv( $fout, $row );
			}
		}
		fclose( $fin );
		fclose( $fout );
		$file_url = trailingslashit( $upload['baseurl'] ) . $this->upload_subdir . '/' . basename( $temp_path );
		wp_send_json_success( [ 'url' => $file_url, 'path' => $temp_path ] );
	}

	/* ---------------------------
	 * Worker: process chunk and write full failures
	 * -------------------------- */

	public function process_job_cron( $arg ) {
		$job_id = '';
		if ( is_array( $arg ) && isset( $arg['job_id'] ) ) {
			$job_id = sanitize_text_field( $arg['job_id'] );
		} elseif ( is_string( $arg ) ) {
			$job_id = sanitize_text_field( $arg );
		} else {
			return;
		}
		$job = $this->get_job( $job_id );
		if ( ! $job ) {
			return;
		}
		if ( $job['status'] === 'processing' ) {
			return;
		}
		$this->update_job( $job_id, [ 'status' => 'processing', 'started_at' => $job['started_at'] ?: current_time( 'mysql' ) ] );
		$file = $job['file_path'];
		if ( ! file_exists( $file ) ) {
			$this->update_job( $job_id, [
				'status'          => 'failed',
				'finished_at'     => current_time( 'mysql' ),
				'failures_count'  => intval( $job['failures_count'] ) + 1,
				'failures_sample' => array_merge( (array) $job['failures_sample'], [ 'CSV file missing' ] ),
			] );
			return;
		}
		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			$this->update_job( $job_id, [
				'status'          => 'failed',
				'finished_at'     => current_time( 'mysql' ),
				'failures_sample' => array_merge( (array) $job['failures_sample'], [ 'Unable to open CSV for reading' ] ),
			] );
			return;
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle );
			$this->update_job( $job_id, [
				'status'          => 'failed',
				'finished_at'     => current_time( 'mysql' ),
				'failures_sample' => array_merge( (array) $job['failures_sample'], [ 'CSV missing header row' ] ),
			] );
			return;
		}
		$orig_header = is_array( $job['original_header'] ) ? $job['original_header'] : $header;
		$mapping     = is_array( $job['mapping'] ) ? $job['mapping'] : ( is_string( $job['mapping'] ) ? maybe_unserialize( $job['mapping'] ) : [] );
		$headers     = array_map( 'trim', $header );
		$field_to_index = [];
		$allowed_fields  = [ 'user_email', 'user_login', 'society_id', 'flat_id', 'member_id', 'create_user' ];
		foreach ( $allowed_fields as $f ) {
			if ( isset( $mapping[ $f ] ) && $mapping[ $f ] !== '' ) {
				$colName = trim( $mapping[ $f ] );
				$idx     = array_search( $colName, $headers, true );
				if ( $idx !== false ) {
					$field_to_index[ $f ] = $idx;
				}
			} else {
				foreach ( $headers as $i => $h ) {
					if ( strtolower( $h ) === strtolower( $f ) ) {
						$field_to_index[ $f ] = $i;
						break;
					}
				}
			}
		}
		$offset  = intval( $job['offset'] );
		$skipped = 0;
		while ( $skipped < $offset && ( $row = fgetcsv( $handle ) ) !== false ) {
			$skipped++;
		}
		$processed       = 0;
		$successes       = 0;
		$failures        = 0;
		$failures_sample = is_array( $job['failures_sample'] ) ? $job['failures_sample'] : [];
		$failure_file    = isset( $job['failures_file'] ) ? $job['failures_file'] : null;
		$failure_fp      = null;
		while ( $processed < $this->chunk_size && ( $row = fgetcsv( $handle ) ) !== false ) {
			$processed++;
			$offset++;
			$get_cell   = function( $field ) use ( $row, $field_to_index ) {
				if ( isset( $field_to_index[ $field ] ) && isset( $row[ $field_to_index[ $field ] ] ) ) {
					return trim( $row[ $field_to_index[ $field ] ] );
				}
				return '';
			};
			$email      = $get_cell( 'user_email' );
			$login      = $get_cell( 'user_login' );
			$society_id = absint( $get_cell( 'society_id' ) );
			$flat_id    = absint( $get_cell( 'flat_id' ) );
			$member_id  = absint( $get_cell( 'member_id' ) );
			$create_user_raw = strtolower( $get_cell( 'create_user' ) );
			$create_user     = in_array( $create_user_raw, [ '1', 'yes', 'true', 'y' ], true );
			$reason = '';
			if ( empty( $email ) && empty( $login ) ) {
				$reason = 'Missing user_email or user_login';
			} elseif ( ! $society_id || ! $flat_id ) {
				$reason = 'Missing society_id or flat_id';
			} elseif ( ! $this->is_job_action_allowed_for_creator( $job, $society_id ) ) {
				$reason = 'Creator not allowed to import for this society';
			} else {
				$belongs = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT id FROM {$this->flats_table} WHERE id = %d AND society_id = %d", $flat_id, $society_id ) );
				if ( ! $belongs ) {
					$reason = 'Flat does not belong to society';
				}
			}
			if ( $reason !== '' ) {
				$failures++;
				if ( count( $failures_sample ) < 50 ) {
					$failures_sample[] = [ 'row' => $offset, 'reason' => $reason, 'data' => $row ];
				}
				if ( ! $failure_file ) {
					$upload = wp_upload_dir();
					$dir    = trailingslashit( $upload['basedir'] ) . $this->upload_subdir;
					if ( ! file_exists( $dir ) ) {
						wp_mkdir_p( $dir );
					}
					$failure_file = $dir . '/' . $job_id . '_failures.csv';
					$this->update_job( $job_id, [ 'failures_file' => $failure_file ] );
				}
				if ( ! $failure_fp ) {
					$failure_fp = fopen( $failure_file, 'a' );
					if ( $failure_fp && filesize( $failure_file ) === 0 ) {
						$header_row = array_merge( [ 'row_number', 'reason' ], $orig_header );
						fputcsv( $failure_fp, $header_row );
					}
				}
				if ( $failure_fp ) {
					$out_row = array_merge( [ $offset, $reason ], $row );
					fputcsv( $failure_fp, $out_row );
				}
				continue;
			}
			// find/create user and map
			$user_obj = null;
			if ( ! empty( $email ) ) {
				$user_obj = get_user_by( 'email', $email );
			}
			if ( ! $user_obj && ! empty( $login ) ) {
				$user_obj = get_user_by( 'login', $login );
			}
			$user_id = 0;
			if ( $user_obj ) {
				$user_id = $user_obj->ID;
			} else {
				if ( $create_user ) {
					$user_login = $login ?: sanitize_user( current( explode( '@', $email ) ) );
					if ( empty( $user_login ) ) {
						$reason = 'Cannot derive username to create account';
						$failures++;
						if ( count( $failures_sample ) < 50 ) {
							$failures_sample[] = [ 'row' => $offset, 'reason' => $reason, 'data' => $row ];
						}
						if ( ! $failure_file ) {
							$upload = wp_upload_dir();
							$dir    = trailingslashit( $upload['basedir'] ) . $this->upload_subdir;
							if ( ! file_exists( $dir ) ) {
								wp_mkdir_p( $dir );
							}
							$failure_file = $dir . '/' . $job_id . '_failures.csv';
							$this->update_job( $job_id, [ 'failures_file' => $failure_file ] );
						}
						if ( ! $failure_fp ) {
							$failure_fp = fopen( $failure_file, 'a' );
							if ( $failure_fp && filesize( $failure_file ) === 0 ) {
								$header_row = array_merge( [ 'row_number', 'reason' ], $orig_header );
								fputcsv( $failure_fp, $header_row );
							}
						}
						if ( $failure_fp ) {
							fputcsv( $failure_fp, array_merge( [ $offset, $reason ], $row ) );
						}
						continue;
					}
					$orig_login = $user_login;
					$i          = 1;
					while ( username_exists( $user_login ) ) {
						$user_login = $orig_login . $i;
						$i++;
					}
					$password   = wp_generate_password( 12, false );
					$create_ret = wp_create_user( $user_login, $password, $email ?: '' );
					if ( is_wp_error( $create_ret ) ) {
						$failures++;
						if ( count( $failures_sample ) < 50 ) {
							$failures_sample[] = [ 'row' => $offset, 'reason' => 'Failed to create user: ' . $create_ret->get_error_message(), 'data' => $row ];
						}
						if ( ! $failure_file ) {
							$upload = wp_upload_dir();
							$dir    = trailingslashit( $upload['basedir'] ) . $this->upload_subdir;
							if ( ! file_exists( $dir ) ) {
								wp_mkdir_p( $dir );
							}
							$failure_file = $dir . '/' . $job_id . '_failures.csv';
							$this->update_job( $job_id, [ 'failures_file' => $failure_file ] );
						}
						if ( ! $failure_fp ) {
							$failure_fp = fopen( $failure_file, 'a' );
							if ( $failure_fp && filesize( $failure_file ) === 0 ) {
								$header_row = array_merge( [ 'row_number', 'reason' ], $orig_header );
								fputcsv( $failure_fp, $header_row );
							}
						}
						if ( $failure_fp ) {
							fputcsv( $failure_fp, array_merge( [ $offset, 'Failed to create user: ' . $create_ret->get_error_message() ], $row ) );
						}
						continue;
					}
					$user_id = (int) $create_ret;
					$this->notify_user_created_via_bulk( $user_id );
				} else {
					$failures++;
					if ( count( $failures_sample ) < 50 ) {
						$failures_sample[] = [ 'row' => $offset, 'reason' => 'User not found', 'data' => $row ];
					}
					if ( ! $failure_file ) {
						$upload = wp_upload_dir();
						$dir    = trailingslashit( $upload['basedir'] ) . $this->upload_subdir;
						if ( ! file_exists( $dir ) ) {
							wp_mkdir_p( $dir );
						}
						$failure_file = $dir . '/' . $job_id . '_failures.csv';
						$this->update_job( $job_id, [ 'failures_file' => $failure_file ] );
					}
					if ( ! $failure_fp ) {
						$failure_fp = fopen( $failure_file, 'a' );
						if ( $failure_fp && filesize( $failure_file ) === 0 ) {
							$header_row = array_merge( [ 'row_number', 'reason' ], $orig_header );
							fputcsv( $failure_fp, $header_row );
						}
					}
					if ( $failure_fp ) {
						fputcsv( $failure_fp, array_merge( [ $offset, 'User not found' ], $row ) );
					}
					continue;
				}
			}
			// Map user to resident
			$wp_user = new \WP_User( $user_id );
			if ( ! in_array( 'sovexxa_flat_resident', (array) $wp_user->roles, true ) ) {
				$wp_user->add_role( 'sovexxa_flat_resident' );
			}
			update_user_meta( $user_id, 'sovexxa_society_id', $society_id );
			update_user_meta( $user_id, 'sovexxa_flat_id', $flat_id );
			if ( $member_id ) {
				update_user_meta( $user_id, 'sovexxa_member_id', $member_id );
			} else {
				delete_user_meta( $user_id, 'sovexxa_member_id' );
			}
			$this->wpdb->insert( $this->audit_table, [
				'user_id'    => $job['created_by'],
				'society_id' => $society_id,
				'action'     => 'bulk_mapped_resident_flat',
				'entity'     => 'user',
				'entity_id'  => $user_id,
				'ip'         => '',
				'created_at' => current_time( 'mysql' ),
			], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ] );
			$this->notify_user_mapped_resident( $user_id, $society_id, $flat_id, $member_id );
			$successes++;
		} // end while

		if ( $failure_fp ) {
			fflush( $failure_fp );
			fclose( $failure_fp );
		}
		fclose( $handle );

		// update job
		$update_fields = [
			'offset'           => $offset,
			'processed_rows'   => intval( $job['processed_rows'] ) + $processed,
			'successes_count'  => intval( $job['successes_count'] ) + $successes,
			'failures_count'   => intval( $job['failures_count'] ) + $failures,
			'failures_sample'  => array_slice( array_merge( (array) $job['failures_sample'], $failures_sample ), 0, 100 ),
		];
		if ( $failure_file ) {
			$update_fields['failures_file'] = $failure_file;
		}
		$this->update_job( $job_id, $update_fields );
		$job = $this->get_job( $job_id );

		if ( intval( $job['processed_rows'] ) >= intval( $job['total_rows'] ) ) {
			$this->update_job( $job_id, [
				'status'      => 'completed',
				'finished_at' => current_time( 'mysql' ),
			] );
			$this->wpdb->insert( $this->audit_table, [
				'user_id'    => intval( $job['created_by'] ),
				'society_id' => 0,
				'action'     => 'bulk_import_completed',
				'entity'     => 'job',
				'entity_id'  => 0,
				'ip'         => '',
				'created_at' => current_time( 'mysql' ),
			], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ] );
			$this->send_job_completion_email( $job );
			return;
		} else {
			// reschedule next chunk
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + 5, 'sovexxa_process_bulk_job', [ 'job_id' => $job_id ], 'sovexxa' );
			} else {
				$bundled = SOVEXXA_PLUGIN_DIR . 'includes/vendor/action-scheduler/autoload.php';
				if ( file_exists( $bundled ) ) {
					require_once $bundled;
					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action( time() + 5, 'sovexxa_process_bulk_job', [ 'job_id' => $job_id ], 'sovexxa' );
					} else {
						if ( ! wp_next_scheduled( 'sovexxa_process_bulk_job', [ $job_id ] ) ) {
							wp_schedule_single_event( time() + 5, 'sovexxa_process_bulk_job', [ $job_id ] );
						}
					}
				} else {
					if ( ! wp_next_scheduled( 'sovexxa_process_bulk_job', [ $job_id ] ) ) {
						wp_schedule_single_event( time() + 5, 'sovexxa_process_bulk_job', [ $job_id ] );
					}
				}
			}
			$this->update_job( $job_id, [ 'status' => 'pending' ] );
			return;
		}
	}

	/* ---------------------------
	 * Completion email
	 * -------------------------- */

	private function send_job_completion_email( $job ) {
		$creator_id    = intval( $job['created_by'] );
		$creator       = get_userdata( $creator_id );
		$creator_email = $creator ? $creator->user_email : '';
		$admin_email   = get_option( 'sovexxa_admin_notification_email', '' );
		$subject       = sprintf( '[%s] Sovexxa Job Completed: %s', get_bloginfo( 'name' ), $job['job_id'] );
		$body          = "Namaskar,\n\n";
		$body         .= "Bulk import job completed.\n\n";
		$body         .= "Job ID: " . $job['job_id'] . "\n";
		$body         .= "Total rows: " . intval( $job['total_rows'] ) . "\n";
		$body         .= "Processed: " . intval( $job['processed_rows'] ) . "\n";
		$body         .= "Successes: " . intval( $job['successes_count'] ) . "\n";
		$body         .= "Failures: " . intval( $job['failures_count'] ) . "\n\n";
		$job_url       = admin_url( 'admin.php?page=sovexxa_jobs&job_id=' . rawurlencode( $job['job_id'] ) );
		$body         .= "Job details: " . $job_url . "\n\n";
		$body         .= "Regards,\n" . get_bloginfo( 'name' );
		if ( $creator_email ) {
			wp_mail( $creator_email, $subject, $body );
		}
		if ( $admin_email && is_email( $admin_email ) ) {
			wp_mail( $admin_email, $subject, $body );
		}
	}

	/* ---------------------------
	 * Audit undo (kept simple)
	 * -------------------------- */

	public function ajax_audit_undo() {
		check_ajax_referer( 'sovexxa_mapping_nonce', 'nonce' );
		$audit_id = isset( $_POST['audit_id'] ) ? absint( $_POST['audit_id'] ) : 0;
		if ( ! $audit_id ) {
			wp_send_json_error( [ 'message' => 'Audit ID required' ] );
		}
		$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->audit_table} WHERE id = %d", $audit_id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( [ 'message' => 'Audit entry not found' ] );
		}
		$society_id = isset( $row['society_id'] ) ? intval( $row['society_id'] ) : 0;
		if ( ! current_user_can( 'sovexxa_manage_all' ) ) {
			if ( ! current_user_can( 'sovexxa_manage_society' ) || ! Security::can_access_society( $society_id ) ) {
				wp_send_json_error( [ 'message' => 'Insufficient capability or access to undo this action' ] );
			}
		}
		$action    = $row['action'];
		$entity    = $row['entity'];
		$entity_id = intval( $row['entity_id'] );
		$undo_result = false;
		$undo_message = '';
		if ( $action === 'mapped_society_admin' && $entity === 'user' && $entity_id ) {
			$wp_user = new \WP_User( $entity_id );
			$wp_user->remove_role( 'sovexxa_society_admin' );
			delete_user_meta( $entity_id, 'sovexxa_society_id' );
			$undo_result = true;
			$undo_message = 'Society Admin unassigned';
		} elseif ( $action === 'unmapped_society_admin' && $entity === 'user' && $entity_id ) {
			if ( $society_id ) {
				$wp_user = new \WP_User( $entity_id );
				if ( ! in_array( 'sovexxa_society_admin', (array) $wp_user->roles, true ) ) {
					$wp_user->add_role( 'sovexxa_society_admin' );
				}
				update_user_meta( $entity_id, 'sovexxa_society_id', $society_id );
				$undo_result = true;
				$undo_message = 'Society Admin reassigned';
			} else {
				$undo_result = false;
				$undo_message = 'Cannot restore society_id';
			}
		} elseif ( ( $action === 'mapped_resident_flat' || $action === 'bulk_mapped_resident_flat' ) && $entity === 'user' && $entity_id ) {
			delete_user_meta( $entity_id, 'sovexxa_society_id' );
			delete_user_meta( $entity_id, 'sovexxa_flat_id' );
			delete_user_meta( $entity_id, 'sovexxa_member_id' );
			$undo_result = true;
			$undo_message = 'Resident mapping removed';
		} else {
			$undo_result = false;
			$undo_message = 'Undo not supported for this audit action';
		}
		$this->wpdb->insert( $this->audit_table, [
			'user_id'    => get_current_user_id(),
			'society_id' => $society_id,
			'action'     => 'undo:' . $action,
			'entity'     => $entity,
			'entity_id'  => $entity_id,
			'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? esc_html( $_SERVER['REMOTE_ADDR'] ) : '',
			'created_at' => current_time( 'mysql' ),
		], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ] );
		if ( $undo_result ) {
			wp_send_json_success( [ 'message' => $undo_message ] );
		}
		wp_send_json_error( [ 'message' => $undo_message ] );
	}

	/* ---------------------------
	 * Helpers: permissions, notifications
	 * -------------------------- */

	private function is_job_action_allowed_for_creator( $job, $society_id ) {
		$creator_id = isset( $job['created_by'] ) ? intval( $job['created_by'] ) : 0;
		if ( ! $creator_id ) {
			return false;
		}
		$creator_user = get_userdata( $creator_id );
		if ( ! $creator_user ) {
			return false;
		}
		if ( user_can( $creator_user, 'sovexxa_manage_all' ) ) {
			return true;
		}
		if ( user_can( $creator_user, 'sovexxa_manage_society' ) ) {
			$creator_soc = get_user_meta( $creator_id, 'sovexxa_society_id', true );
			return ( $creator_soc && intval( $creator_soc ) === intval( $society_id ) );
		}
		return false;
	}

	private function notify_user_assigned_society_admin( $user_id, $society_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}
		$soc = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT name FROM {$this->societies_table} WHERE id = %d", $society_id ), ARRAY_A );
		$socname = $soc ? $soc['name'] : '';
		$subject = sprintf( '[%s] You have been assigned as Society Admin', get_bloginfo( 'name' ) );
		$message = "Namaskar,\n\nYou have been assigned the role of Society Admin for the society: {$socname}.\n\nLogin: " . wp_login_url() . "\n\nRegards,\n" . get_bloginfo( 'name' );
		wp_mail( $user->user_email, $subject, $message );
	}

	private function notify_user_unassigned_society_admin( $user_id, $society_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}
		$socname = '';
		if ( $society_id ) {
			$soc = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT name FROM {$this->societies_table} WHERE id = %d", $society_id ), ARRAY_A );
			$socname = $soc ? $soc['name'] : '';
		}
		$subject = sprintf( '[%s] You have been unassigned as Society Admin', get_bloginfo( 'name' ) );
		$message = "Namaskar,\n\nYou have been unassigned from the Society Admin role for the society: {$socname}.\n\nRegards,\n" . get_bloginfo( 'name' );
		wp_mail( $user->user_email, $subject, $message );
	}

	private function notify_user_mapped_resident( $user_id, $society_id, $flat_id, $member_id = null ) {
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}
		$soc = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT name FROM {$this->societies_table} WHERE id = %d", $society_id ), ARRAY_A );
		$socname = $soc ? $soc['name'] : '';
		$flat = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT flat_number FROM {$this->flats_table} WHERE id = %d", $flat_id ), ARRAY_A );
		$flatnum = $flat ? $flat['flat_number'] : '';
		$subject = sprintf( '[%s] You have been mapped to a flat', get_bloginfo( 'name' ) );
		$message = "Namaskar,\n\nYou have been mapped to Flat: {$flatnum} in Society: {$socname}.\n\nPortal / Login: " . wp_login_url() . "\n\nRegards,\n" . get_bloginfo( 'name' );
		wp_mail( $user->user_email, $subject, $message );
	}

	private function notify_user_created_via_bulk( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}
		$subject = sprintf( '[%s] Your account has been created', get_bloginfo( 'name' ) );
		$message = "Namaskar,\n\nAn account has been created for you on " . get_bloginfo( 'name' ) . ".\n\nUsername: " . $user->user_login . "\nPlease reset your password here: " . wp_lostpassword_url() . "\n\nRegards,\n" . get_bloginfo( 'name' );
		wp_mail( $user->user_email, $subject, $message );
	}
}