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
