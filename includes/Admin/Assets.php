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

		$this->enqueue_overview( (string) $hook );
	}

	/**
	 * The React overview, on the landing screen only.
	 *
	 * Skipped entirely when the build is not present - a checkout of the repository
	 * without a build step should show the server-rendered screen, not a blank panel.
	 */
	private function enqueue_overview( string $hook ): void {
		if ( 'toplevel_page_' . Menu::SLUG !== $hook ) {
			return;
		}

		$ui       = SUBKIT_PATH . 'build/ui.asset.php';
		$overview = SUBKIT_PATH . 'build/overview.asset.php';

		if ( ! is_readable( $ui ) || ! is_readable( $overview ) ) {
			return;
		}

		$ui       = require $ui;
		$overview = require $overview;

		wp_enqueue_style( 'subkit-ui', SUBKIT_URL . 'build/ui.css', array(), $ui['version'] );
		wp_style_add_data( 'subkit-ui', 'rtl', 'replace' );
		wp_enqueue_script( 'subkit-ui', SUBKIT_URL . 'build/ui.js', $ui['dependencies'], $ui['version'], true );

		wp_enqueue_script(
			'subkit-overview',
			SUBKIT_URL . 'build/overview.js',
			array_merge( $overview['dependencies'], array( 'subkit-ui' ) ),
			$overview['version'],
			true
		);

		wp_set_script_translations( 'subkit-overview', 'subkit-subscriptions', SUBKIT_PATH . 'languages' );
		wp_set_script_translations( 'subkit-ui', 'subkit-subscriptions', SUBKIT_PATH . 'languages' );
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
