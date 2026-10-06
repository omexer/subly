<?php
/**
 * PSR-4 style autoloader for the Subly\ namespace. No Composer, no production dependencies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'Subly\\' ) ) {
				return;
		}

		$relative = substr( $class, strlen( 'Subly\\' ) );
		$path     = SUBLY_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
