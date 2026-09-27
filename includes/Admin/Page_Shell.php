<?php

namespace SubKit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The frame every SubKit screen sits in: a header bar with breadcrumbs, the page heading,
 * and a footer.
 *
 * One frame for every screen in both plugins, so a merchant moving from Subscriptions to
 * Reports to Integrations stays in one product rather than a string of unrelated pages.
 */
class Page_Shell {

	public const UPGRADE_URL = 'https://github.com/pronob1010/subkit-subscriptions-pro';

	/**
	 * @param string                                   $title    The page heading.
	 * @param string                                   $subtitle One line under it, or empty.
	 * @param array<int, array{label: string, url?: string}> $crumbs Trail after Home; the last is the current page.
	 * @param string                                   $actions  Already-escaped buttons for the right of the heading.
	 * @param bool                                     $visible  False when the screen draws its own heading; it stays for screen readers.
	 */
	public static function open( string $title, string $subtitle = '', array $crumbs = array(), string $actions = '', bool $visible = true ): void {
		$links = self::header_links();

		if ( ! $crumbs ) {
			$crumbs = array( array( 'label' => $title ) );
		}

		echo '<div class="subkit-shell">';
		echo '<header class="subkit-shell__bar"><div class="subkit-shell__inner">';

		echo '<nav class="subkit-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'subkit-subscriptions' ) . '">';
		printf(
			'<a class="subkit-crumbs__home" href="%s"><img class="subkit-logo" src="%s" width="178" height="28" alt="%s"></a>',
			esc_url( $links['home'] ),
			esc_url( $links['logo'] ),
			esc_attr__( 'EasySubscription', 'subkit-subscriptions' )
		);

		$last = count( $crumbs ) - 1;

		foreach ( array_values( $crumbs ) as $index => $crumb ) {
			echo '<span class="subkit-crumbs__sep" aria-hidden="true">/</span>';

			if ( $index < $last && ! empty( $crumb['url'] ) ) {
				printf( '<a class="subkit-crumbs__link" href="%s">%s</a>', esc_url( (string) $crumb['url'] ), esc_html( (string) $crumb['label'] ) );
			} else {
				printf( '<span class="subkit-crumbs__current" aria-current="page">%s</span>', esc_html( (string) $crumb['label'] ) );
			}
		}

		echo '</nav>';

		echo '<div class="subkit-shell__meta">';

		Notices::render_toggle();

		printf(
			'<a href="%s">%s%s</a><a href="%s">%s%s</a>',
			esc_url( $links['help'] ),
			self::icon( '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .8-1 1.5v.4M12 16.6h.01"/>' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html__( 'Help', 'subkit-subscriptions' ),
			esc_url( $links['settings'] ),
			self::icon( '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html__( 'Settings', 'subkit-subscriptions' )
		);

		if ( '' !== $links['upgrade'] ) {
			printf(
				'<a class="subkit-shell__upgrade" href="%s" target="_blank" rel="noopener noreferrer">%s%s<span class="screen-reader-text"> %s</span></a>',
				esc_url( $links['upgrade'] ),
				self::icon( '<path d="M3 8l4.5 4L12 6l4.5 6L21 8l-2 10H5z"/>' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
				esc_html__( 'Upgrade to Pro', 'subkit-subscriptions' ),
				/* translators: accessibility text for a link that opens in a new tab */
				esc_html__( '(opens in a new tab)', 'subkit-subscriptions' )
			);
		}

		echo '</div></div></header>';

		echo '<div class="wrap subkit-shell__body">';
		echo '<div class="subkit-head' . ( $visible ? '' : ' screen-reader-text' ) . '"><div class="subkit-head__text">';
		echo '<h1 class="subkit-head__title">' . esc_html( $title ) . '</h1>';

		if ( '' !== $subtitle ) {
			echo '<p class="subkit-head__subtitle">' . esc_html( $subtitle ) . '</p>';
		}

		echo '</div>';

		if ( '' !== $actions ) {
			echo '<div class="subkit-head__actions">' . wp_kses_post( $actions ) . '</div>';
		}

		echo '</div>';

		// WordPress moves admin notices to just below this marker; without it they land
		// wherever the first heading happens to be, which inside a custom layout is anywhere.
		echo '<hr class="wp-header-end">';

		Notices::render_important();
	}

	public static function close(): void {
		printf(
			'</div><footer class="subkit-shell__foot">%s &middot; <a href="%s">%s</a></footer></div>',
			/* translators: %s: version number */
			esc_html( sprintf( __( 'EasySubscription %s', 'subkit-subscriptions' ), SUBKIT_VERSION ) ),
			esc_url( admin_url( 'admin.php?page=' . Menu::SLUG . '-help' ) ),
			esc_html__( 'Help and system report', 'subkit-subscriptions' )
		);
	}

	/**
	 * The header's links, shared with the app shell so both headers go to the same places.
	 *
	 * @return array{home: string, help: string, settings: string, upgrade: string, logo: string}
	 */
	public static function header_links(): array {
		return array(
			'logo'     => SUBKIT_URL . 'assets/images/logo.svg',
			'home'     => admin_url( 'admin.php?page=' . Menu::SLUG ),
			'help'     => admin_url( 'admin.php?page=' . Help_Page::SLUG ),
			'settings' => admin_url( 'admin.php?page=' . Settings_Page::SLUG ),
			'upgrade'  => did_action( 'subkit_pro_loaded' ) ? '' : self::UPGRADE_URL,
		);
	}

	private static function icon( string $paths ): string {
		return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
	}
}
