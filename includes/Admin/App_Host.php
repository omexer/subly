<?php

namespace SubKit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every EasySubscription admin page is a route of one React app, hosted here.
 *
 * Each page still prints its own server render inside the host, which stays the screen
 * until the app has drawn the route and is what a browser without JavaScript sees.
 */
final class App_Host {

	public const HANDLE = 'subkit-shell';

	public const BODY_CLASS = 'subkit-app-page';

	private bool $enqueued = false;

	public static function start( string $page ): void {
		printf( '<div id="subkit-app" class="subkit-ui" data-page="%s"></div><div id="subkit-fallback">', esc_attr( $page ) );
	}

	public static function end(): void {
		echo '</div>';
	}

	/**
	 * @return string[]
	 */
	public static function pages(): array {
		/**
		 * Filter the admin page slugs that are routes of the EasySubscription app.
		 *
		 * @param string[] $pages
		 */
		$pages = (array) apply_filters(
			'subkit_app_pages',
			array(
				Menu::SLUG,
				Menu::LIST_SLUG,
				Integrations_Page::SLUG,
				Help_Page::SLUG,
				Settings_Page::SLUG,
			)
		);

		return array_values( array_unique( array_map( 'strval', $pages ) ) );
	}

	public function register(): void {
		// After Assets, which registers the shared UI this depends on.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Marks a page the shell will draw, which is what lets the stylesheet hide its notices before the shell moves them.
	 *
	 * @param string $classes
	 */
	public function body_class( $classes ): string {
		return $this->enqueued ? trim( (string) $classes . ' ' . self::BODY_CLASS ) : (string) $classes;
	}

	public function enqueue(): void {
		global $plugin_page;

		$this->enqueued = false;

		if ( ! is_string( $plugin_page ) || ! in_array( $plugin_page, self::pages(), true ) ) {
			return;
		}

		$asset = SUBKIT_PATH . 'build/shell.asset.php';

		if ( ! is_readable( $asset ) || ! wp_script_is( 'subkit-ui', 'registered' ) ) {
			return;
		}

		$asset = require $asset;

		wp_enqueue_style( 'subkit-ui' );
		wp_enqueue_style( self::HANDLE, SUBKIT_URL . 'build/shell.css', array( 'subkit-ui' ), $asset['version'] );
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

		wp_enqueue_script(
			self::HANDLE,
			SUBKIT_URL . 'build/shell.js',
			array_merge( $asset['dependencies'], array( 'subkit-ui' ) ),
			$asset['version'],
			true
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.subkitShellData = ' . wp_json_encode( array( 'links' => Page_Shell::header_links() ) ) . ';',
			'before'
		);

		wp_set_script_translations( self::HANDLE, 'subkit-subscriptions', SUBKIT_PATH . 'languages' );

		$this->enqueued = true;

		/**
		 * Fires on every EasySubscription app page, once the shell is enqueued.
		 *
		 * Route bundles enqueue themselves here, with App_Host::HANDLE as a dependency.
		 */
		do_action( 'subkit_app_enqueue' );
	}
}
