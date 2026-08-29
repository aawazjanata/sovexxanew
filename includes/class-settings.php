<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic settings page for global options
 */
class Settings {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_menu() {
		add_submenu_page(
			'sovexxa_dashboard',
			__( 'Sovexxa Settings', 'sovexxa' ),
			__( 'Settings', 'sovexxa' ),
			'sovexxa_manage_settings',
			'sovexxa_settings',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'sovexxa_settings_group', 'sovexxa_admin_notification_email', [
			'type' => 'string',
			'description' => 'Email to notify on job completion',
			'sanitize_callback' => 'sanitize_email',
			'default' => get_option( 'admin_email' ),
		] );
		register_setting( 'sovexxa_settings_group', 'sovexxa_chunk_size', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 100,
		] );
		register_setting( 'sovexxa_settings_group', 'sovexxa_use_bundled_as', [
			'type' => 'boolean',
			'sanitize_callback' => [ $this, 'sanitize_bool' ],
			'default' => false,
		] );
	}

	public function sanitize_bool( $v ) {
		return $v ? 1 : 0;
	}

	public function render_page() {
		if ( ! current_user_can( 'sovexxa_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'sovexxa' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sovexxa Settings', 'sovexxa' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'sovexxa_settings_group' ); do_settings_sections( 'sovexxa_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="sovexxa_admin_notification_email"><?php esc_html_e( 'Job Notification Email', 'sovexxa' ); ?></label></th>
						<td><input type="email" id="sovexxa_admin_notification_email" name="sovexxa_admin_notification_email" value="<?php echo esc_attr( get_option( 'sovexxa_admin_notification_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="sovexxa_chunk_size"><?php esc_html_e( 'Bulk Job Chunk Size', 'sovexxa' ); ?></label></th>
						<td><input type="number" id="sovexxa_chunk_size" name="sovexxa_chunk_size" value="<?php echo esc_attr( get_option( 'sovexxa_chunk_size', 100 ) ); ?>" min="1" /></td>
					</tr>
					<tr>
						<th><label for="sovexxa_use_bundled_as"><?php esc_html_e( 'Use bundled Action Scheduler (if present)', 'sovexxa' ); ?></label></th>
						<td><input type="checkbox" id="sovexxa_use_bundled_as" name="sovexxa_use_bundled_as" value="1" <?php checked( get_option( 'sovexxa_use_bundled_as', 0 ), 1 ); ?> /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}