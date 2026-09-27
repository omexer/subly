<?php
/**
 * PSR-4 style autoloader for the EasySubscription\ namespace. No Composer, no production dependencies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'EasySubscription\\' ) ) {
				return;
		}

		$relative = substr( $class, strlen( 'EasySubscription\\' ) );
		$path     = EASYSUBSCRIPTION_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
