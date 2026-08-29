<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security helpers for capability and society scoping checks
 */
class Security {

	/**
	 * Return society id for current user (if assigned)
	 * @return int|null
	 */
	public static function get_current_user_society_id() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return null;
		}
		$soc = get_user_meta( $user_id, 'sovexxa_society_id', true );
		return $soc ? intval( $soc ) : null;
	}

	/**
	 * Check if current user can access given society
	 */
	public static function can_access_society( $society_id ) {
		if ( current_user_can( 'sovexxa_manage_all' ) ) {
			return true;
		}
		$my = self::get_current_user_society_id();
		return ( $my && intval( $my ) === intval( $society_id ) );
	}

	/**
	 * Check if user (id) can access society
	 */
	public static function user_can_access_society( $user_id, $society_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		if ( user_can( $user, 'sovexxa_manage_all' ) ) {
			return true;
		}
		$my = get_user_meta( $user_id, 'sovexxa_society_id', true );
		return ( $my && intval( $my ) === intval( $society_id ) );
	}

	/**
	 * Escapes an array of inputs (recursive)
	 */
	public static function esc_array( $arr ) {
		if ( ! is_array( $arr ) ) {
			return esc_html( $arr );
		}
		$out = [];
		foreach ( $arr as $k => $v ) {
			if ( is_array( $v ) ) {
				$out[ $k ] = self::esc_array( $v );
			} else {
				$out[ $k ] = sanitize_text_field( wp_unslash( $v ) );
			}
		}
		return $out;
	}
}