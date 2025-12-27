<?php

namespace StackBoost\ForSupportCandy\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Service for generating PDFs using Dompdf.
 */
class PdfService {

	/**
	 * The singleton instance.
	 *
	 * @var PdfService|null
	 */
	private static $instance = null;

	/**
	 * Private constructor to prevent instantiation.
	 */
	private function __construct() {
		// Load the Dompdf autoloader if it hasn't been loaded yet.
		$autoloader = \STACKBOOST_PLUGIN_PATH . 'includes/libraries/dompdf/autoload.inc.php';
		if ( file_exists( $autoloader ) ) {
			require_once $autoloader;
		}
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return PdfService
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Generate a PDF from HTML content.
	 *
	 * @param string $html    The HTML content to render.
	 * @param array  $options Optional configuration options for Dompdf.
	 *                        e.g., ['paper' => 'A4', 'orientation' => 'portrait'].
	 * @return string|false The generated PDF content as a string, or false on failure.
	 */
	public function generate_pdf( $html, $options = [] ) {
		if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
			stackboost_log( 'Dompdf library not found.', 'error' );
			return false;
		}

		try {
			$dompdf_options = new Options();
			$dompdf_options->set( 'defaultFont', 'Helvetica' );
			$dompdf_options->set( 'isHtml5ParserEnabled', true );
			// Strict GDPR compliance: Disable remote resources to prevent leakage.
			$dompdf_options->set( 'isRemoteEnabled', false );

			// Allow override/addition of options
			if ( isset( $options['dompdf_options'] ) && is_array( $options['dompdf_options'] ) ) {
				foreach ( $options['dompdf_options'] as $key => $value ) {
					$dompdf_options->set( $key, $value );
				}
			}

			$dompdf = new Dompdf( $dompdf_options );
			$dompdf->loadHtml( $html );

			// Set paper size and orientation
			$paper       = isset( $options['paper'] ) ? $options['paper'] : 'A4';
			$orientation = isset( $options['orientation'] ) ? $options['orientation'] : 'portrait';
			$dompdf->setPaper( $paper, $orientation );

			$dompdf->render();

			return $dompdf->output();
		} catch ( \Exception $e ) {
			stackboost_log( 'PDF Generation Failed: ' . $e->getMessage(), 'error' );
			return false;
		}
	}
}
