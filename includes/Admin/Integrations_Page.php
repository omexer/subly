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

		echo '<div class="wrap"><h1>' . esc_html__( 'Integrations', 'subkit-subscriptions' ) . '</h1>';

		if ( ! $integrations ) {
			echo '<p>' . esc_html__( 'Integrations connect a subscription to the plugin that grants what it pays for — a course, a mailing list, a licence key. SubKit Pro adds them.', 'subkit-subscriptions' ) . '</p></div>';

			return;
		}

		echo '<p class="description">' . esc_html__( 'An integration only does anything while the plugin it connects to is active.', 'subkit-subscriptions' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:46rem"><thead><tr><th>'
			. esc_html__( 'Integration', 'subkit-subscriptions' ) . '</th><th>'
			. esc_html__( 'Needs', 'subkit-subscriptions' ) . '</th><th>'
			. esc_html__( 'Status', 'subkit-subscriptions' ) . '</th></tr></thead><tbody>';

		foreach ( $integrations as $integration ) {
			$active = ! empty( $integration['active'] );

			printf(
				'<tr><td><strong>%s</strong></td><td>%s</td><td><span style="color:%s">%s</span></td></tr>',
				esc_html( (string) ( $integration['title'] ?? '' ) ),
				esc_html( (string) ( $integration['requires'] ?? '' ) ),
				$active ? '#1a7f37' : '#646970',
				esc_html(
					$active
						? __( 'Connected', 'subkit-subscriptions' )
						: __( 'Plugin not active', 'subkit-subscriptions' )
				)
			);
		}

		echo '</tbody></table></div>';
	}
}
