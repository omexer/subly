<?php
/**
 * PSR-4 style autoloader for the SubKit\ namespace. No Composer, no production dependencies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register( function ( $class ) {
	if ( 0 !== strpos( $class, 'SubKit\\' ) ) {
		return;
	}

	$relative = substr( $class, strlen( 'SubKit\\' ) );
	$path     = SUBKIT_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_readable( $path ) ) {
		require_once $path;
	}
} );
