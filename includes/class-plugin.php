<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    private $user_mapping;
    private $audit_log;
    private $database;

    public function __construct() {
        $this->database    = new Database();
        $this->user_mapping= new User_Mapping();
        $this->audit_log   = new Audit_Log();
    }

    public function run() {
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'init', [ $this, 'maybe_upgrade_db' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'public_assets' ] );
    }

    public function maybe_upgrade_db() {
        $this->database->maybe_upgrade();
    }

    public function register_admin_menu() {
        add_menu_page(
            __( 'Sovexxa', 'sovexxa' ),
            __( 'Sovexxa', 'sovexxa' ),
            'sovexxa_manage_all',
            'sovexxa_dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-building',
            56
        );

        add_submenu_page(
            'sovexxa_dashboard',
            __( 'Society Admins', 'sovexxa' ),
            __( 'Society Admins', 'sovexxa' ),
            'sovexxa_manage_all',
            'sovexxa_society_admins',
            [ $this, 'render_society_admins' ]
        );

        add_submenu_page(
            'sovexxa_dashboard',
            __( 'Resident Mapping', 'sovexxa' ),
            __( 'Resident Mapping', 'sovexxa' ),
            'sovexxa_manage_society',
            'sovexxa_resident_mapping',
            [ $this, 'render_resident_mapping' ]
        );

        add_submenu_page(
            'sovexxa_dashboard',
            __( 'Bulk Jobs', 'sovexxa' ),
            __( 'Bulk Jobs', 'sovexxa' ),
            'sovexxa_manage_society',
            'sovexxa_jobs',
            [ $this, 'render_jobs_admin' ]
        );

        add_submenu_page(
            'sovexxa_dashboard',
            __( 'Audit Log', 'sovexxa' ),
            __( 'Audit Log', 'sovexxa' ),
            'sovexxa_manage_society',
            'sovexxa_audit_log',
            [ $this, 'render_audit_log' ]
        );
    }

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

    public function public_assets() {
        // enqueue public assets later as needed
    }

    /* ---------------------------
     * Renderers: admin pages (minimal)
     * -------------------------- */

    public function render_dashboard() {
        if ( ! current_user_can( 'sovexxa_manage_society' ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
            wp_die( esc_html__( 'Access Denied', 'sovexxa' ) );
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Sovexxa Dashboard', 'sovexxa' ) . '</h1>';
        echo '<p>' . esc_html__( 'Welcome to Sovexxa Society Management System.', 'sovexxa' ) . '</p>';
        echo '</div>';
    }

    public function render_society_admins() {
        if ( ! current_user_can( 'sovexxa_manage_all' ) ) {
            wp_die( esc_html__( 'Access Denied', 'sovexxa' ) );
        }
        global $wpdb;
        echo '<div class="wrap"><h1>' . esc_html__( 'Society Admins', 'sovexxa' ) . '</h1>';
        ?>
        <h2><?php echo esc_html( 'Assign Society Admin' ); ?></h2>
        <form id="sovexxa-assign-society-admin">
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Search User', 'sovexxa' ); ?></label></th>
                    <td>
                        <input type="text" id="sovexxa-search-user" placeholder="<?php esc_attr_e( 'Type name, email or username', 'sovexxa' ); ?>" />
                        <select id="sovexxa-search-results" style="min-width:300px;"></select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Select Society', 'sovexxa' ); ?></label></th>
                    <td>
                        <select id="sovexxa-select-society">
                            <option value=""><?php esc_html_e( 'Select society', 'sovexxa' ); ?></option>
                            <?php
                            $rows = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}sovexxa_societies ORDER BY name ASC", ARRAY_A );
                            foreach ( $rows as $r ) {
                                echo '<option value="' . esc_attr( $r['id'] ) . '">' . esc_html( $r['name'] ) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
            <input type="hidden" id="sovexxa-map-user-id" name="user_id" value="">
            <input type="hidden" id="sovexxa-map-society-id" name="society_id" value="">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'sovexxa_mapping_nonce' ) ); ?>">
            <p><button class="button button-primary" id="sovexxa-assign-society-admin-btn"><?php esc_html_e( 'Assign as Society Admin', 'sovexxa' ); ?></button></p>
        </form>

        <hr/>
        <h2><?php echo esc_html( 'Existing Society Admins' ); ?></h2>

        <form id="sovexxa-bulk-unassign-form">
        <p><button class="button" id="sovexxa-bulk-unassign-btn"><?php esc_html_e( 'Bulk Unassign Selected', 'sovexxa' ); ?></button></p>
        <table class="widefat striped">
            <thead><tr><th><input type="checkbox" id="sovexxa-select-all-admins" /></th><th>User</th><th>Society</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $admins = get_users( [ 'role' => 'sovexxa_society_admin', 'number' => 500 ] );
            foreach ( $admins as $a ) {
                $sid = get_user_meta( $a->ID, 'sovexxa_society_id', true );
                $socname = '';
                if ( $sid ) {
                    $soc = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}sovexxa_societies WHERE id = %d", $sid ), ARRAY_A );
                    $socname = $soc ? $soc['name'] : '';
                }
                echo '<tr>';
                echo '<td><input type="checkbox" class="sovexxa-admin-checkbox" value="' . esc_attr( $a->ID ) . '"></td>';
                echo '<td>' . esc_html( $a->display_name . ' (' . $a->user_email . ')' ) . '</td>';
                echo '<td>' . esc_html( $socname ) . '</td>';
                echo '<td><button class="button sovexxa-unassign-admin" data-userid="' . esc_attr( $a->ID ) . '">Unassign</button></td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
        </form>
        <?php
        echo '</div>';
    }

    public function render_resident_mapping() {
        if ( ! current_user_can( 'sovexxa_manage_society' ) && ! current_user_can( 'sovexxa_manage_all' ) ) {
            wp_die( esc_html__( 'Access Denied', 'sovexxa' ) );
        }
        global $wpdb;
        echo '<div class="wrap"><h1>' . esc_html__( 'Resident Mapping', 'sovexxa' ) . '</h1>';
        ?>
        <h2><?php echo esc_html( 'Bulk Import Residents (CSV) with Column Mapping' ); ?></h2>
        <form id="sovexxa-bulk-import-form" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'CSV File', 'sovexxa' ); ?></label></th>
                    <td><input type="file" name="csv_file" id="sovexxa-csv-file" accept=".csv" required></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Detected Headers', 'sovexxa' ); ?></label></th>
                    <td>
                        <div id="sovexxa-csv-headers-area">
                            <p class="description"><?php esc_html_e( 'Select a CSV file to detect headers. Then map each desired field.' ); ?></p>
                        </div>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'sovexxa_mapping_nonce' ) ); ?>">
            <p><button class="button button-primary" id="sovexxa-bulk-import-btn"><?php esc_html_e( 'Upload and Process', 'sovexxa' ); ?></button></p>
        </form>

        <div id="sovexxa-bulk-import-result" style="margin-top:20px;"></div>
        <?php
        echo '</div>';
    }

    public function render_jobs_admin() {
        // This method delegates rendering to the user_mapping/audit_log methods earlier provided.
        // For simplicity we'll include the job list via a small wrapper which uses the DB directly.
        $mapping = $this->user_mapping;
        include SOVEXXA_PLUGIN_DIR . 'admin/views/jobs-admin.php';
    }

    public function render_audit_log() {
        $this->audit_log->render_admin_page();
    }
}
