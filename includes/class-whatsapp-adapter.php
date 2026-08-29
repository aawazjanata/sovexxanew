<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sample WhatsApp Business API adapter.
 *
 * This adapter sends messages using a configured provider endpoint and token.
 * Configure options:
 * - sovexxa_whatsapp_api_url
 * - sovexxa_whatsapp_api_token
 *
 * It registers a filter so sovexxa_whatsapp_send uses this adapter if configured.
 */
class WhatsApp_Adapter {

	public function __construct() {
		add_filter( 'sovexxa_whatsapp_send', [ $this, 'send_message' ], 10, 4 );
	}

	/**
	 * $phone: string (international without '+'), $message: text, $options: array
	 */
	public function send_message( $fallback, $phone, $message, $options = [] ) {
		$api_url = get_option( 'sovexxa_whatsapp_api_url', '' );
		$api_token = get_option( 'sovexxa_whatsapp_api_token', '' );
		if ( empty( $api_url ) || empty( $api_token ) ) {
			return false;
		}
		$payload = [
			'to' => $phone,
			'message' => $message,
		];
		$ch = curl_init( $api_url );
		$headers = [
			'Authorization: Bearer ' . $api_token,
			'Content-Type: application/json',
		];
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $payload ) );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
		$res = curl_exec( $ch );
		$errno = curl_errno( $ch );
		$http = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		if ( $errno || $http < 200 || $http >= 300 ) {
			return false;
		}
		return wp_remote_retrieve_body( wp_remote_get( $api_url ) ) ? true : true;
	}
}