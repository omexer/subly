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

	/**
	 * @param string                                   $title    The page heading.
	 * @param string                                   $subtitle One line under it, or empty.
	 * @param array<int, array{label: string, url?: string}> $crumbs Trail after Home; the last is the current page.
	 * @param string                                   $actions  Already-escaped buttons for the right of the heading.
	 * @param bool                                     $visible  False when the screen draws its own heading; it stays for screen readers.
	 */
	public static function open( string $title, string $subtitle = '', array $crumbs = array(), string $actions = '', bool $visible = true ): void {
		$home = admin_url( 'admin.php?page=' . Menu::SLUG );

		if ( ! $crumbs ) {
			$crumbs = array( array( 'label' => $title ) );
		}

		echo '<div class="subkit-shell">';
		echo '<header class="subkit-shell__bar"><div class="subkit-shell__inner">';

		echo '<nav class="subkit-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'subkit-subscriptions' ) . '">';
		printf(
			'<a class="subkit-crumbs__home" href="%s">%s<span>%s</span></a>',
			esc_url( $home ),
			self::mark(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html__( 'SubKit', 'subkit-subscriptions' )
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

		printf(
			'<div class="subkit-shell__meta"><a href="%s">%s</a><a href="%s">%s</a></div>',
			esc_url( admin_url( 'admin.php?page=' . Menu::SLUG . '-help' ) ),
			esc_html__( 'Help', 'subkit-subscriptions' ),
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=subkit' ) ),
			esc_html__( 'Settings', 'subkit-subscriptions' )
		);

		echo '</div></header>';

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
	}

	public static function close(): void {
		printf(
			'</div><footer class="subkit-shell__foot">%s &middot; <a href="%s">%s</a></footer></div>',
			/* translators: %s: version number */
			esc_html( sprintf( __( 'SubKit %s', 'subkit-subscriptions' ), SUBKIT_VERSION ) ),
			esc_url( admin_url( 'admin.php?page=' . Menu::SLUG . '-help' ) ),
			esc_html__( 'Help and system report', 'subkit-subscriptions' )
		);
	}

	/**
	 * The mark: a renewal loop in a rounded square. Drawn here so there is no image
	 * request, and so it takes the brand colour from the stylesheet.
	 */
	public static function mark(): string {
		return '<svg class="subkit-mark" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
			. '<rect width="24" height="24" rx="6" fill="currentColor"/>'
			. '<path d="M16.5 9.2A5 5 0 0 0 7.4 10M7.5 14.8a5 5 0 0 0 9.1-.8" fill="none" stroke="#fff" stroke-width="1.9" stroke-linecap="round"/>'
			. '<path d="M16.9 6.6v2.9H14M7.1 17.4v-2.9H10" fill="none" stroke="#fff" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>'
			. '</svg>';
	}
}
