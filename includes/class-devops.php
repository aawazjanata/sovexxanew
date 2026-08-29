<?php
namespace Sovexxa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Small dev helper: create a ZIP of the plugin for distribution.
 * Use from CLI (php tools/build-zip.php) or adapt.
 */
class DevOps {

	/**
	 * Create zip archive of plugin directory (excluding node_modules, tests).
	 * $source: path to plugin folder; $dest: full path to zip file
	 */
	public static function create_zip( $source, $dest ) {
		if ( ! extension_loaded( 'zip' ) ) {
			throw new \Exception( 'Zip extension not available' );
		}
		$source = realpath( $source );
		if ( ! file_exists( $source ) ) {
			throw new \Exception( 'Source not found: ' . $source );
		}
		$zip = new \ZipArchive();
		if ( ! $zip->open( $dest, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			throw new \Exception( 'Unable to open zip for writing: ' . $dest );
		}
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $files as $name => $file ) {
			if ( ! $file->isDir() ) {
				$filePath = $file->getRealPath();
				$relativePath = substr( $filePath, strlen( $source ) + 1 );
				// exclude tests, node_modules, vendor/action-scheduler (optional)
				if ( preg_match( '#(^tests/|/tests/|node_modules/|vendor/|includes/vendor/action-scheduler)#', $relativePath ) ) {
					continue;
				}
				$zip->addFile( $filePath, $relativePath );
			}
		}
		$zip->close();
		return $dest;
	}
}