<?php

namespace SubKit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What SubKit can connect to, and whether each connection is live.
 *
 * The free plugin ships no integrations of its own. This screen exists so a merchant can
 * see what is possible and what is missing, rather than discovering that an integration
 * silently did nothing because its plugin was never installed.
 */
class Integrations_Page {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 30 );
	}

	public function add_page(): void {
		add_submenu_page(
			Menu::PARENT,
			__( 'Integrations', 'subkit-subscriptions' ),
			__( 'Integrations', 'subkit-subscriptions' ),
			Menu::CAPABILITY,
			Menu::SLUG . '-integrations',
			array( $this, 'render' )
		);
	}

	/**
	 * Each entry is title, the plugin it needs, and whether that plugin is here.
	 *
	 * @return array<int, array{title: string, requires: string, active: bool, url: string}>
	 */
	public function integrations(): array {
		/**
		 * Filter the integrations shown on the Integrations screen.
		 *
		 * @param array $integrations
		 */
		$integrations = (array) apply_filters( 'subkit_admin_integrations', array() );

		usort(
			$integrations,
			static fn( array $a, array $b ): int => strcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) )
		);

		return $integrations;
	}

	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage subscriptions.', 'subkit-subscriptions' ) );
		}

		$integrations = $this->integrations();

		echo '<div class="wrap subkit-page"><h1>' . esc_html__( 'Integrations', 'subkit-subscriptions' ) . '</h1>';

		if ( ! $integrations ) {
			printf(
				'<div class="subkit-card"><div class="subkit-empty"><p class="subkit-empty__title">%s</p><p>%s</p></div></div></div>',
				esc_html__( 'Nothing to connect yet', 'subkit-subscriptions' ),
				esc_html__( 'Integrations hand a subscription to the plugin that grants what it pays for — a course, a mailing list, a licence key. SubKit Pro adds them.', 'subkit-subscriptions' )
			);

			return;
		}

		echo '<p class="subkit-lede">' . esc_html__( 'An integration only does anything while the plugin it connects to is active.', 'subkit-subscriptions' ) . '</p>';
		echo '<table class="widefat striped subkit-table subkit-facts"><thead><tr><th>'
			. esc_html__( 'Integration', 'subkit-subscriptions' ) . '</th><th>'
			. esc_html__( 'Needs', 'subkit-subscriptions' ) . '</th><th>'
			. esc_html__( 'Status', 'subkit-subscriptions' ) . '</th><th></th></tr></thead><tbody>';

		foreach ( $integrations as $integration ) {
			$active = ! empty( $integration['active'] );

			printf(
				'<tr><th scope="row">%s</th><td>%s</td><td><span class="subkit-pill subkit-pill--%s">%s</span></td><td>',
				esc_html( (string) ( $integration['title'] ?? '' ) ),
				esc_html( (string) ( $integration['requires'] ?? '' ) ),
				$active ? 'sk-active' : 'sk-cancelled',
				esc_html(
					$active
						? __( 'Connected', 'subkit-subscriptions' )
						: __( 'Plugin not active', 'subkit-subscriptions' )
				)
			);

			$this->render_action( $integration, $active );

			echo '</td></tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Install where we can, link out where the plugin is sold rather than hosted.
	 */
	private function render_action( array $integration, bool $active ): void {
		if ( $active ) {
			return;
		}

		$slug = (string) ( $integration['slug'] ?? '' );

		if ( '' !== $slug && current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' ) ) {
			printf(
				'<button type="button" class="button button-small subkit-install" data-slug="%s">%s</button>',
				esc_attr( $slug ),
				esc_html__( 'Install', 'subkit-subscriptions' )
			);

			return;
		}

		$url = (string) ( $integration['url'] ?? '' );

		if ( '' === $url ) {
			return;
		}

		printf(
			'<a class="button button-small" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $url ),
			esc_html__( 'Get it', 'subkit-subscriptions' )
		);
	}
}
