<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WhatsApp utility placeholder
 * Provides helper to build click-to-chat URLs and a pluggable adapter for Business API.
 */
class WhatsApp {

	public function __construct() {
		// nothing yet - pluggable adapter
	}

	/**
	 * Build a WhatsApp click-to-chat URL with prefilled message
	 * phone: international format without plus (e.g., 919999888777)
	 * message: plain text
	 */
	public static function click_to_chat_url( $phone, $message ) {
		$encoded = rawurlencode( $message );
		return "https://wa.me/{$phone}?text={$encoded}";
	}

	/**
	 * Send via Business API adapter (pluggable)
	 * Adapter can be set via do_action or filter; here we provide a filter placeholder.
	 */
	public static function send_business_message( $phone, $message, $options = [] ) {
		// Example filter hook for adapter
		$result = apply_filters( 'sovexxa_whatsapp_send', false, $phone, $message, $options );
		return $result;
	}
}