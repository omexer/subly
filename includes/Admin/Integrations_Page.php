<?php

namespace EasySubscription\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What EasySubscription can connect to, and whether each connection is live.
 *
 * The free plugin ships no integrations of its own. This screen exists so a merchant can
 * see what is possible and what is missing, rather than discovering that an integration
 * silently did nothing because its plugin was never installed.
 */
class Integrations_Page {

	public const SLUG = Menu::SLUG . '-integrations';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 30 );
	}

	public function add_page(): void {
		add_submenu_page(
			Menu::PARENT,
			__( 'Integrations', 'easysubscription' ),
			__( 'Integrations', 'easysubscription' ),
			Menu::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Each entry is title, the plugin it needs, whether that plugin is here, and where it is set up.
	 *
	 * @return array<int, array{title: string, requires: string, active: bool, url: string, configure_url?: string, configure_label?: string, hint?: string}>
	 */
	public function integrations(): array {
		/**
		 * Filter the integrations shown on the Integrations screen.
		 *
		 * @param array $integrations
		 */
		$integrations = (array) apply_filters( 'easysubscription_admin_integrations', array() );

		usort(
			$integrations,
			static fn( array $a, array $b ): int => strcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) )
		);

		return $integrations;
	}

	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage subscriptions.', 'easysubscription' ) );
		}

		App_Host::start( self::SLUG );
		$this->render_screen();
		App_Host::end();
	}

	private function render_screen(): void {
		$integrations = $this->integrations();

		Page_Shell::open(
			__( 'Integrations', 'easysubscription' ),
			__( 'Hand a subscription to the plugin that delivers what it pays for, and take it back when it ends.', 'easysubscription' )
		);

		if ( ! $integrations ) {
			printf(
				'<div class="easysubscription-card"><div class="easysubscription-empty"><p class="easysubscription-empty__title">%s</p><p>%s</p></div></div>',
				esc_html__( 'Nothing to connect yet', 'easysubscription' ),
				esc_html__( 'Integrations hand a subscription to the plugin that grants what it pays for — a course, a mailing list, a licence key. EasySubscription Pro adds them.', 'easysubscription' )
			);

			Page_Shell::close();
			return;
		}

		$groups = array();

		foreach ( $integrations as $integration ) {
			$category              = (string) ( $integration['category'] ?? '' );
			$category              = '' !== $category ? $category : __( 'Other', 'easysubscription' );
			$groups[ $category ][] = $integration;
		}

		foreach ( $groups as $category => $items ) {
			echo '<h2 class="easysubscription-section-title">' . esc_html( $category ) . '</h2><div class="easysubscription-grid">';

			foreach ( $items as $integration ) {
				$this->render_tile( $integration );
			}

			echo '</div>';
		}

		echo '<p class="easysubscription-lede" style="margin-top:24px">' . esc_html__( 'An integration only does anything while the plugin it connects to is active, and only for products you have configured it on.', 'easysubscription' ) . '</p>';

		Page_Shell::close();
	}

	/**
	 * @param array<string, mixed> $integration
	 */
	private function render_tile( array $integration ): void {
		$active      = ! empty( $integration['active'] );
		$title       = (string) ( $integration['title'] ?? '' );
		$description = (string) ( $integration['description'] ?? '' );

		echo '<div class="easysubscription-tile"><div class="easysubscription-tile__top">';

		$this->render_icon( $integration );

		printf(
			'<div><h3 class="easysubscription-tile__title">%s</h3><p class="easysubscription-tile__meta">%s</p></div></div>',
			esc_html( $title ),
			esc_html(
				sprintf(
					/* translators: %s: the plugin an integration needs */
					__( 'Needs %s', 'easysubscription' ),
					(string) ( $integration['requires'] ?? $title )
				)
			)
		);

		if ( '' !== $description ) {
			echo '<p class="easysubscription-tile__body">' . esc_html( $description ) . '</p>';
		}

		$hint = (string) ( $integration['hint'] ?? '' );

		if ( '' !== $hint ) {
			echo '<p class="easysubscription-tile__meta">' . esc_html( $hint ) . '</p>';
		}

		printf(
			'<div class="easysubscription-tile__foot"><span class="easysubscription-badge %s">%s</span><span class="easysubscription-tile__action">',
			$active ? 'easysubscription-badge--good' : '',
			esc_html( $active ? __( 'Connected', 'easysubscription' ) : __( 'Not active', 'easysubscription' ) )
		);

		$this->render_configure( $integration );
		$this->render_action( $integration, $active );

		echo '</span></div></div>';
	}

	/**
	 * The plugin's WordPress.org icon when it has one, otherwise its initial: a broken
	 * image in a grid of logos looks like the integration itself is broken.
	 *
	 * @param array<string, mixed> $integration
	 */
	private function render_icon( array $integration ): void {
		$title = (string) ( $integration['title'] ?? '?' );
		$icon  = (string) ( $integration['icon'] ?? '' );
		$first = strtoupper( function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 ) );

		if ( '' === $icon ) {
			printf( '<span class="easysubscription-tile__icon" aria-hidden="true">%s</span>', esc_html( $first ) );
			return;
		}

		printf(
			'<span class="easysubscription-tile__icon" aria-hidden="true" data-initial="%s"><img src="%s" alt="" width="40" height="40" loading="lazy" onerror="this.parentNode.textContent=this.parentNode.dataset.initial"></span>',
			esc_attr( $first ),
			esc_url( $icon )
		);
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
				'<button type="button" class="easysubscription-btn easysubscription-btn--primary easysubscription-btn--sm easysubscription-install" data-slug="%s">%s</button>',
				esc_attr( $slug ),
				esc_html__( 'Install', 'easysubscription' )
			);

			return;
		}

		$url = (string) ( $integration['url'] ?? '' );

		if ( '' === $url ) {
			return;
		}

		printf(
			'<a class="easysubscription-btn easysubscription-btn--sm" href="%s" target="_blank" rel="noopener noreferrer">%s <span aria-hidden="true">&#8599;</span></a>',
			esc_url( $url ),
			esc_html__( 'Get it', 'easysubscription' )
		);
	}

	/**
	 * @param array<string, mixed> $integration
	 */
	private function render_configure( array $integration ): void {
		$url = (string) ( $integration['configure_url'] ?? '' );

		if ( '' === $url ) {
			return;
		}

		$label = (string) ( $integration['configure_label'] ?? '' );

		printf(
			'<a class="easysubscription-btn easysubscription-btn--sm" href="%s">%s</a>',
			esc_url( $url ),
			esc_html( '' !== $label ? $label : __( 'Settings', 'easysubscription' ) )
		);
	}
}
