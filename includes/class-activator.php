<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {

	public static function activate() {
		// Create DB tables
		$db = new Database();
		$db->create_tables();

		// Create roles and capabilities
		self::create_roles();

		// Option default
		add_option( 'sovexxa_admin_notification_email', get_option( 'admin_email' ) );
	}

	private static function create_roles() {
		// Define capabilities
		$capabilities = [
			'sovexxa_manage_all'       => true,
			'sovexxa_manage_society'   => true,
			'sovexxa_manage_flats'     => true,
			'sovexxa_manage_members'   => true,
			'sovexxa_manage_committee' => true,
			'sovexxa_manage_bills'     => true,
			'sovexxa_manage_payments'  => true,
			'sovexxa_manage_notices'   => true,
			'sovexxa_manage_complaints'=> true,
			'sovexxa_manage_reports'   => true,
			'sovexxa_view_reports'     => true,
			'sovexxa_manage_settings'  => true,
		];

		// Sovexxa Super Admin - grant all sovexxa capabilities + manage_options
		add_role( 'sovexxa_super_admin', 'Sovexxa Super Admin', array_merge( $capabilities, [ 'manage_options' => true ] ) );

		// Society Admin - society-scoped admin (no manage_options)
		add_role( 'sovexxa_society_admin', 'Sovexxa Society Admin', $capabilities );

		// Flat Resident
		add_role( 'sovexxa_flat_resident', 'Sovexxa Flat Resident', [
			'sovexxa_view_reports' => true,
		] );
	}

}