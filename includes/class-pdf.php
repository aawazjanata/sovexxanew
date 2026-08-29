<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PDF helper: render HTML and use Dompdf if available to create PDF binary.
 * Usage:
 *   $pdf = new Sovexxa\PDF();
 *   $binary = $pdf->render_html_to_pdf( $html );
 */
class PDF {

	/**
	 * Render HTML to PDF binary using Dompdf if available.
	 * Returns binary string on success, false on failure.
	 */
	public function render_html_to_pdf( $html, $options = [] ) {
		// Try to locate Dompdf classes
		if ( class_exists( '\Dompdf\Dompdf' ) ) {
			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( $options['paper'] ?? 'A4', $options['orientation'] ?? 'portrait' );
			$dompdf->render();
			return $dompdf->output();
		}
		// Dompdf not present; return HTML so caller can handle it
		return false;
	}

	/**
	 * Stream a PDF download (uses Dompdf if present). If Dompdf missing, streams HTML as a fallback.
	 */
	public function stream_pdf_response( $html, $filename = 'document.pdf', $options = [] ) {
		$binary = $this->render_html_to_pdf( $html, $options );
		if ( $binary !== false ) {
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
			echo $binary;
			exit;
		}
		// Fallback: send HTML
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . basename( preg_replace( '/\.pdf$/', '.html', $filename ) ) . '"' );
		echo $html;
		exit;
	}
}