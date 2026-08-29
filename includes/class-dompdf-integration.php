<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * DOMPDF integration wrapper.
 * If dompdf is available via composer/vendor, uses it for PDF generation.
 * If not available, methods return false and callers should fallback to HTML.
 */
class DomPDF_Integration {

	/**
	 * Return binary PDF or false
	 */
	public static function html_to_pdf( $html, $options = [] ) {
		// prefer our PDF class if implemented
		if ( class_exists( '\\Dompdf\\Dompdf' ) ) {
			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( $options['paper'] ?? 'A4', $options['orientation'] ?? 'portrait' );
			$dompdf->render();
			return $dompdf->output();
		}
		// try vendor autoload (if composer not loaded by WP)
		$vendor = SOVEXXA_PLUGIN_DIR . 'vendor/autoload.php';
		if ( file_exists( $vendor ) ) {
			require_once $vendor;
			if ( class_exists( '\\Dompdf\\Dompdf' ) ) {
				$dompdf = new \Dompdf\Dompdf( $options );
				$dompdf->loadHtml( $html );
				$dompdf->setPaper( $options['paper'] ?? 'A4', $options['orientation'] ?? 'portrait' );
				$dompdf->render();
				return $dompdf->output();
			}
		}
		return false;
	}
}