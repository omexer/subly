<?php

namespace SubKit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin stylesheet, on SubKit screens only.
 *
 * Pro's screens are SubKit screens, so they inherit it without importing anything - which
 * is why every class here is shared rather than namespaced per plugin.
 */
class Assets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Marks SubKit's own screens so the frame can take over the page edge. Not the
	 * WooCommerce settings tab: that screen is WooCommerce's, and has no frame.
	 *
	 * @param string $classes
	 */
	public function body_class( $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && str_contains( (string) $screen->id, Menu::SLUG )
			? trim( (string) $classes . ' subkit-admin' )
			: (string) $classes;
	}

	/**
	 * @param string $hook
	 */
	public function enqueue( $hook ): void {
		if ( ! $this->is_subkit_screen( (string) $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'subkit-admin',
			SUBKIT_URL . 'assets/css/admin.css',
			array(),
			SUBKIT_VERSION
		);

		wp_enqueue_script(
			'subkit-admin',
			SUBKIT_URL . 'assets/js/admin.js',
			array(),
			SUBKIT_VERSION,
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
			'subkit_admin_script_data',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'installAction' => current_user_can( 'install_plugins' ) ? Integration_Installer::ACTION : '',
				'installNonce'  => wp_create_nonce( Integration_Installer::ACTION ),
				'i18n'          => array(
					'install'    => __( 'Install', 'subkit-subscriptions' ),
					'installing' => __( 'Installing…', 'subkit-subscriptions' ),
					'done'       => __( 'Done.', 'subkit-subscriptions' ),
					'failed'     => __( 'That did not work.', 'subkit-subscriptions' ),
				),
			)
		);

		wp_localize_script( 'subkit-admin', 'subkitAdmin', $data );

		$this->register_ui();
		$this->enqueue_screens( (string) $hook );
	}

	/**
	 * Register the shared UI on every SubKit screen, enqueue it on none.
	 *
	 * Registered rather than enqueued because SubKit Pro's screens depend on the handle:
	 * a screen that uses it asks for it, and a screen that does not costs nothing.
	 */
	private function register_ui(): void {
		$asset = SUBKIT_PATH . 'build/ui.asset.php';

		if ( ! is_readable( $asset ) ) {
			return;
		}

		$asset = require $asset;

		wp_register_style( 'subkit-ui', SUBKIT_URL . 'build/ui.css', array(), $asset['version'] );
		wp_style_add_data( 'subkit-ui', 'rtl', 'replace' );

		wp_register_script( 'subkit-ui', SUBKIT_URL . 'build/ui.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'subkit-ui', 'subkit-subscriptions', SUBKIT_PATH . 'languages' );
	}

	/**
	 * Home gets the dashboard; the list page gets the list and detail screens.
	 *
	 * Hook names come from get_plugin_page_hookname() rather than being spelled out: the
	 * prefix is the menu title run through sanitize_title(), so a translated title would
	 * silently change it and nothing would load.
	 */
	private function enqueue_screens( string $hook ): void {
		if ( ! wp_script_is( 'subkit-ui', 'registered' ) ) {
			return;
		}

		$screens = array(
			get_plugin_page_hookname( Menu::SLUG, '' )             => 'dashboard',
			get_plugin_page_hookname( Menu::LIST_SLUG, Menu::SLUG ) => 'subscriptions',
		);

		if ( ! isset( $screens[ $hook ] ) ) {
			return;
		}

		wp_enqueue_style( 'subkit-ui' );
		$this->enqueue_bundle( $screens[ $hook ] );
	}

	private function enqueue_bundle( string $bundle ): void {
		$asset = SUBKIT_PATH . 'build/' . $bundle . '.asset.php';

		if ( ! is_readable( $asset ) ) {
			return;
		}

		$asset  = require $asset;
		$handle = 'subkit-' . $bundle;

		wp_enqueue_script(
			$handle,
			SUBKIT_URL . 'build/' . $bundle . '.js',
			array_merge( $asset['dependencies'], array( 'subkit-ui' ) ),
			$asset['version'],
			true
		);

		wp_set_script_translations( $handle, 'subkit-subscriptions', SUBKIT_PATH . 'languages' );
	}

	private function is_subkit_screen( string $hook ): bool {
		if ( str_contains( $hook, Menu::SLUG ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen routing.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		// Our own tab of the WooCommerce settings screen, and nobody else's.
		return 'woocommerce_page_wc-settings' === $hook && 'subkit' === $tab;
	}
}
