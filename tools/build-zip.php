<?php
/**
 * Build ZIP for plugin distribution.
 * Usage (CLI):
 *   php tools/build-zip.php /path/to/plugin-folder /path/to/output/sovexxa.zip
 */
require_once __DIR__ . '/../includes/class-devops.php';

if ( php_sapi_name() !== 'cli' ) {
    echo "This script must be run from CLI.\n";
    exit(1);
}

$source = $argv[1] ?? null;
$dest = $argv[2] ?? null;

if ( ! $source || ! $dest ) {
    echo "Usage: php tools/build-zip.php /path/to/plugin /path/to/output.zip\n";
    exit(1);
}

try {
    $zip = Sovexxa\DevOps::create_zip( $source, $dest );
    echo "Created zip: $zip\n";
} catch ( Exception $e ) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(2);
}