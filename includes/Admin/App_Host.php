<?php

namespace EasySubscription\Admin;

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

	public const HANDLE = 'easysubscription-shell';

	public const BODY_CLASS = 'easysubscription-app-page';

	private bool $enqueued = false;

	public static function start( string $page ): void {
		printf( '<div id="easysubscription-app" class="easysubscription-ui" data-page="%s"></div><div id="easysubscription-fallback">', esc_attr( $page ) );
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
			'easysubscription_app_pages',
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

		$asset = EASYSUBSCRIPTION_PATH . 'build/shell.asset.php';

		if ( ! is_readable( $asset ) || ! wp_script_is( 'easysubscription-ui', 'registered' ) ) {
			return;
		}

		$asset = require $asset;

		wp_enqueue_style( 'easysubscription-ui' );
		wp_enqueue_style( self::HANDLE, EASYSUBSCRIPTION_URL . 'build/shell.css', array( 'easysubscription-ui' ), $asset['version'] );
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

		wp_enqueue_script(
			self::HANDLE,
			EASYSUBSCRIPTION_URL . 'build/shell.js',
			array_merge( $asset['dependencies'], array( 'easysubscription-ui' ) ),
			$asset['version'],
			true
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.easysubscriptionShellData = ' . wp_json_encode( array( 'links' => Page_Shell::header_links() ) ) . ';',
			'before'
		);

		wp_set_script_translations( self::HANDLE, 'easysubscription', EASYSUBSCRIPTION_PATH . 'languages' );

		$this->enqueued = true;

		/**
		 * Fires on every EasySubscription app page, once the shell is enqueued.
		 *
		 * Route bundles enqueue themselves here, with App_Host::HANDLE as a dependency.
		 */
		do_action( 'easysubscription_app_enqueue' );
	}
}
