<?php

namespace EasySubscription\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin stylesheet, on EasySubscription screens only.
 *
 * Pro's screens are EasySubscription screens, so they inherit it without importing anything - which
 * is why every class here is shared rather than namespaced per plugin.
 */
class Assets {

	/**
	 * The file's own timestamp, so an edited asset is never served from cache between releases.
	 */
	public static function version( string $relative ): string {
		$mtime = @filemtime( EASYSUBSCRIPTION_PATH . $relative ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file just falls back to the version.

		return $mtime ? EASYSUBSCRIPTION_VERSION . '.' . $mtime : EASYSUBSCRIPTION_VERSION;
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'easysubscription_app_enqueue', array( $this, 'enqueue_routes' ) );
	}

	/**
	 * Marks EasySubscription's own screens so the frame can take over the page edge. Not the
	 * WooCommerce settings tab: that screen is WooCommerce's, and has no frame.
	 *
	 * @param string $classes
	 */
	public function body_class( $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && str_contains( (string) $screen->id, Menu::SLUG )
			? trim( (string) $classes . ' easysubscription-admin' )
			: (string) $classes;
	}

	/**
	 * @param string $hook
	 */
	public function enqueue( $hook ): void {
		if ( ! $this->is_easysubscription_screen( (string) $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'easysubscription-admin',
			EASYSUBSCRIPTION_URL . 'assets/css/admin.css',
			array(),
			self::version( 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			'easysubscription-admin',
			EASYSUBSCRIPTION_URL . 'assets/js/admin.js',
			array(),
			self::version( 'assets/js/admin.js' ),
			true
		);

		/**
		 * Filter the data handed to the admin script.
		 *
		 * Pro adds its own action names and nonces here rather than enqueueing a second
		 * script for a few hundred bytes.
		 *
		 * @param array $data
		 */
		$data = (array) apply_filters(
			'easysubscription_admin_script_data',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'installAction' => current_user_can( 'install_plugins' ) ? Integration_Installer::ACTION : '',
				'installNonce'  => wp_create_nonce( Integration_Installer::ACTION ),
				'i18n'          => array(
					'install'    => __( 'Install', 'easysubscription' ),
					'installing' => __( 'Installing…', 'easysubscription' ),
					'done'       => __( 'Done.', 'easysubscription' ),
					'failed'     => __( 'That did not work.', 'easysubscription' ),
				),
			)
		);

		wp_localize_script( 'easysubscription-admin', 'easysubscriptionAdmin', $data );

		$this->register_ui();
	}

	/**
	 * Register the shared UI on every EasySubscription screen, enqueue it on none.
	 *
	 * Registered rather than enqueued because EasySubscription Pro's screens depend on the handle:
	 * a screen that uses it asks for it, and a screen that does not costs nothing.
	 */
	private function register_ui(): void {
		$asset = EASYSUBSCRIPTION_PATH . 'build/ui.asset.php';

		if ( ! is_readable( $asset ) ) {
			return;
		}

		$asset = require $asset;

		wp_register_style( 'easysubscription-ui', EASYSUBSCRIPTION_URL . 'build/ui.css', array(), $asset['version'] );
		wp_style_add_data( 'easysubscription-ui', 'rtl', 'replace' );

		wp_register_script( 'easysubscription-ui', EASYSUBSCRIPTION_URL . 'build/ui.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'easysubscription-ui', 'easysubscription', EASYSUBSCRIPTION_PATH . 'languages' );
	}

	/**
	 * Every free route on every app page, so moving between them needs no page load.
	 */
	public function enqueue_routes(): void {
		foreach ( array( 'dashboard', 'subscriptions', 'integrations', 'help' ) as $bundle ) {
			$this->enqueue_bundle( $bundle );
		}
	}

	private function enqueue_bundle( string $bundle ): void {
		$asset = EASYSUBSCRIPTION_PATH . 'build/' . $bundle . '.asset.php';

		if ( ! is_readable( $asset ) ) {
			return;
		}

		$asset  = require $asset;
		$handle = 'easysubscription-' . $bundle;

		wp_enqueue_script(
			$handle,
			EASYSUBSCRIPTION_URL . 'build/' . $bundle . '.js',
			array_merge( $asset['dependencies'], array( 'easysubscription-ui', App_Host::HANDLE ) ),
			$asset['version'],
			true
		);

		wp_set_script_translations( $handle, 'easysubscription', EASYSUBSCRIPTION_PATH . 'languages' );
	}

	private function is_easysubscription_screen( string $hook ): bool {
		if ( str_contains( $hook, Menu::SLUG ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen routing.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		// Our own tab of the WooCommerce settings screen, and nobody else's.
		return 'woocommerce_page_wc-settings' === $hook && 'easysubscription' === $tab;
	}
}
