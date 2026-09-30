<?php

namespace EasySubscription\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The frame every EasySubscription screen sits in: a header bar with breadcrumbs, the page heading,
 * and a footer.
 *
 * One frame for every screen in both plugins, so a merchant moving from Subscriptions to
 * Reports to Integrations stays in one product rather than a string of unrelated pages.
 */
class Page_Shell {

	public const UPGRADE_URL = 'https://github.com/pronob1010/easysubscription-pro';

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

		echo '<div class="easysubscription-shell">';
		echo '<header class="easysubscription-shell__bar"><div class="easysubscription-shell__inner">';

		echo '<nav class="easysubscription-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'easysubscription' ) . '">';
		printf(
			'<a class="easysubscription-crumbs__home" href="%s"><img class="easysubscription-logo" src="%s" width="178" height="28" alt="%s"></a>',
			esc_url( $links['home'] ),
			esc_url( $links['logo'] ),
			esc_attr__( 'EasySubscription', 'easysubscription' )
		);

		$last = count( $crumbs ) - 1;

		foreach ( array_values( $crumbs ) as $index => $crumb ) {
			echo '<span class="easysubscription-crumbs__sep" aria-hidden="true">/</span>';

			if ( $index < $last && ! empty( $crumb['url'] ) ) {
				printf( '<a class="easysubscription-crumbs__link" href="%s">%s</a>', esc_url( (string) $crumb['url'] ), esc_html( (string) $crumb['label'] ) );
			} else {
				printf( '<span class="easysubscription-crumbs__current" aria-current="page">%s</span>', esc_html( (string) $crumb['label'] ) );
			}
		}

		echo '</nav>';

		echo '<div class="easysubscription-shell__meta">';

		Notices::render_toggle();

		printf(
			'<a href="%s">%s%s</a><a href="%s">%s%s</a>',
			esc_url( $links['help'] ),
			self::icon( '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .8-1 1.5v.4M12 16.6h.01"/>' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html__( 'Help', 'easysubscription' ),
			esc_url( $links['settings'] ),
			self::icon( '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html__( 'Settings', 'easysubscription' )
		);

		if ( '' !== $links['upgrade'] ) {
			printf(
				'<a class="easysubscription-shell__upgrade" href="%s" target="_blank" rel="noopener noreferrer">%s%s<span class="screen-reader-text"> %s</span></a>',
				esc_url( $links['upgrade'] ),
				self::icon( '<path d="M3 8l4.5 4L12 6l4.5 6L21 8l-2 10H5z"/>' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
				esc_html__( 'Upgrade to Pro', 'easysubscription' ),
				/* translators: accessibility text for a link that opens in a new tab */
				esc_html__( '(opens in a new tab)', 'easysubscription' )
			);
		}

		echo '</div></div></header>';

		echo '<div class="wrap easysubscription-shell__body">';
		echo '<div class="easysubscription-head' . ( $visible ? '' : ' screen-reader-text' ) . '"><div class="easysubscription-head__text">';
		echo '<h1 class="easysubscription-head__title">' . esc_html( $title ) . '</h1>';

		if ( '' !== $subtitle ) {
			echo '<p class="easysubscription-head__subtitle">' . esc_html( $subtitle ) . '</p>';
		}

		echo '</div>';

		if ( '' !== $actions ) {
			echo '<div class="easysubscription-head__actions">' . wp_kses_post( $actions ) . '</div>';
		}

		echo '</div>';

		// WordPress moves admin notices to just below this marker; without it they land
		// wherever the first heading happens to be, which inside a custom layout is anywhere.
		echo '<hr class="wp-header-end">';

		Notices::render_important();
	}

	public static function close(): void {
		printf(
			'</div><footer class="easysubscription-shell__foot">%s &middot; <a href="%s">%s</a></footer></div>',
			/* translators: %s: version number */
			esc_html( sprintf( __( 'EasySubscription %s', 'easysubscription' ), EASYSUBSCRIPTION_VERSION ) ),
			esc_url( admin_url( 'admin.php?page=' . Menu::SLUG . '-help' ) ),
			esc_html__( 'Help and system report', 'easysubscription' )
		);
	}

	/**
	 * The header's links, shared with the app shell so both headers go to the same places.
	 *
	 * @return array{home: string, help: string, settings: string, upgrade: string, logo: string}
	 */
	public static function header_links(): array {
		return array(
			'logo'     => EASYSUBSCRIPTION_URL . 'assets/images/logo.svg',
			'home'     => admin_url( 'admin.php?page=' . Menu::SLUG ),
			'help'     => admin_url( 'admin.php?page=' . Help_Page::SLUG ),
			'settings' => admin_url( 'admin.php?page=' . Settings_Page::SLUG ),
			'upgrade'  => self::offers_upgrade() ? self::UPGRADE_URL : '',
		);
	}

	/**
	 * Off for now: the easysubscription_show_upgrade filter brings the header button back.
	 */
	private static function offers_upgrade(): bool {
		return ! did_action( 'easysubscription_pro_loaded' ) && (bool) apply_filters( 'easysubscription_show_upgrade', false );
	}

	private static function icon( string $paths ): string {
		return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
	}
}
