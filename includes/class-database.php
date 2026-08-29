<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database {

    private $wpdb;
    private $charset_collate;
    private $prefix;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->prefix = $wpdb->prefix;
    }

    public function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables_sql = [];

        // Societies table
        $tables_sql[] = "CREATE TABLE {$this->prefix}sovexxa_societies (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            registration_no VARCHAR(191) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            district VARCHAR(100) DEFAULT NULL,
            state VARCHAR(100) DEFAULT NULL,
            pincode VARCHAR(20) DEFAULT NULL,
            contact_number VARCHAR(30) DEFAULT NULL,
            email VARCHAR(191) DEFAULT NULL,
            formation_date DATE DEFAULT NULL,
            bank_name VARCHAR(191) DEFAULT NULL,
            account_name VARCHAR(191) DEFAULT NULL,
            account_number VARCHAR(64) DEFAULT NULL,
            ifsc_code VARCHAR(32) DEFAULT NULL,
            logo VARCHAR(255) DEFAULT NULL,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY (status)
        ) {$this->charset_collate};";

        // Wings
        $tables_sql[] = "CREATE TABLE {$this->prefix}sovexxa_wings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            society_id BIGINT UNSIGNED NOT NULL,
            wing_name VARCHAR(191) NOT NULL,
            wing_code VARCHAR(50) DEFAULT NULL,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY society_id (society_id)
        ) {$this->charset_collate};";

        // Floors
        $tables_sql[] = "CREATE TABLE {$this->prefix}sovexxa_floors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            society_id BIGINT UNSIGNED NOT NULL,
            wing_id BIGINT UNSIGNED DEFAULT NULL,
            floor_number VARCHAR(50) DEFAULT NULL,
            floor_name VARCHAR(191) DEFAULT NULL,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY society_id (society_id),
            KEY wing_id (wing_id)
        ) {$this->charset_collate};";

        // Flats
        $tables_sql[] = "CREATE TABLE {$this->prefix}sovexxa_flats (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            society_id BIGINT UNSIGNED NOT NULL,
            wing_id BIGINT UNSIGNED DEFAULT NULL,
            floor_id BIGINT UNSIGNED DEFAULT NULL,
            flat_number VARCHAR(64) NOT NULL,
            flat_type VARCHAR(64) DEFAULT NULL,
            area VARCHAR(64) DEFAULT NULL,
            ownership_status VARCHAR(64) DEFAULT NULL,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY society_id (society_id),
            KEY wing_id (wing_id),
            KEY floor_id (floor_id)
        ) {$this->charset_collate};";

        // Members
        $tables_sql[] = "CREATE TABLE {$this->prefix}sovexxa_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            society_id BIGINT UNSIGNED NOT NULL,
            flat_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            full_name VARCHAR(191) NOT NULL,
            mobile VARCHAR(30) DEFAULT NULL,
            email VARCHAR(191) DEFAULT NULL,
            relation VARCHAR(64) DEFAULT NULL,
            member_type VARCHAR(64) DEFAULT NULL,
            gender VARCHAR(16) DEFAULT NULL,
            date_of_birth DATE DEFAULT NULL,
            profile_photo VARCHAR(255) DEFAULT NULL,
            is_primary TINYINT DEFAULT 0,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY society_id (society_id),
            KEY flat_id (flat_id),
            KEY user_id (user_id)
        ) {$this->charset_collate};";

        // Committee members
        $tables_sql[] = "CREATE TABLE {$this->prefix}sovexxa_committee_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            society_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(191) NOT NULL,
            position VARCHAR(191) DEFAULT NULL,
            mobile VARCHAR(30) DEFAULT NULL,
            email VARCHAR(191) DEFAULT NULL,
            photo VARCHAR(255) DEFAULT NULL,
            start_date DATE DEFAULT NULL,
            end_date DATE DEFAULT NULL,
            status TINYINT DEFAULT 1,
            display_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY society_id (society_id)
        ) {$this->charset_collate};";

        // Audit log
        $tables_sql[] = "CREATE TABLE {$this->prefix}sovexxa_audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            society_id BIGINT UNSIGNED DEFAULT NULL,
            action VARCHAR(191) NOT NULL,
            entity VARCHAR(191) DEFAULT NULL,
            entity_id BIGINT UNSIGNED DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY society_id (society_id),
            KEY action (action)
        ) {$this->charset_collate};";

        // Bulk jobs table
        $tables_sql[] = "CREATE TABLE {$this->prefix}sovexxa_bulk_jobs (
            job_id VARCHAR(64) NOT NULL,
            file_path TEXT NOT NULL,
            file_name VARCHAR(191) DEFAULT NULL,
            mapping LONGTEXT DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            total_rows INT DEFAULT 0,
            processed_rows INT DEFAULT 0,
            offset INT DEFAULT 0,
            successes_count INT DEFAULT 0,
            failures_count INT DEFAULT 0,
            failures_sample LONGTEXT DEFAULT NULL,
            failures_file VARCHAR(255) DEFAULT NULL,
            original_header LONGTEXT DEFAULT NULL,
            parent_job_id VARCHAR(64) DEFAULT NULL,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME DEFAULT NULL,
            finished_at DATETIME DEFAULT NULL,
            PRIMARY KEY (job_id),
            KEY (status),
            KEY (created_by),
            KEY (parent_job_id)
        ) {$this->charset_collate};";

        // Run dbDelta
        foreach ( $tables_sql as $sql ) {
            dbDelta( $sql );
        }

        update_option( 'sovexxa_db_version', SOVEXXA_DB_VERSION );
    }

    public function maybe_upgrade() {
        $current = get_option( 'sovexxa_db_version', '' );
        if ( $current !== SOVEXXA_DB_VERSION ) {
            $this->create_tables();
            update_option( 'sovexxa_db_version', SOVEXXA_DB_VERSION );
        }
    }
}
