<?php

namespace SubKit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * On our screens, notices marked IMPORTANT stay under the header; every other one moves into the header's notification centre.
 */
class Notices {

	public const IMPORTANT = 'subkit-notice--important';

	public const FEEDBACK = 'subkit-notice--feedback';

	private const HOOKS = array( 'admin_notices', 'all_admin_notices', 'user_admin_notices' );

	/**
	 * @var array<string, array<int, callable>>
	 */
	private array $tucked = array();

	private string $html = '';

	private string $important = '';

	private int $count = 0;

	private bool $shown = false;

	private bool $placed = false;

	private static ?self $current = null;

	/**
	 * Classes for a notice that stays in view because money or renewals are at risk now.
	 */
	public static function important( string $type, bool $dismissible = false ): string {
		return 'notice notice-' . $type . ( $dismissible ? ' is-dismissible' : '' ) . ' ' . self::IMPORTANT;
	}

	/**
	 * Classes for the result of what the merchant just did: in view, and only on the page it was done on.
	 */
	public static function feedback( string $type, bool $dismissible = false ): string {
		return self::important( $type, $dismissible ) . ' ' . self::FEEDBACK;
	}

	public function register(): void {
		self::$current = $this;

		add_action( 'in_admin_header', array( $this, 'collect' ), 1 );
		// Last resort: a SubKit screen that draws no shell would otherwise swallow them.
		add_action( 'admin_footer', array( $this, 'render_leftovers' ), 99 );
	}

	public function collect(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! str_contains( (string) $screen->id, Menu::SLUG ) ) {
			return;
		}

		foreach ( self::HOOKS as $hook ) {
			$this->tucked[ $hook ] = $this->take( $hook );
			add_action( $hook, array( $this, 'run' ) );
		}
	}

	/**
	 * Runs the callbacks that were on the notice hook now firing, sorting what each one printed.
	 */
	public function run(): void {
		$hook = current_action();

		foreach ( $this->tucked[ $hook ] ?? array() as $callback ) {
			ob_start();
			call_user_func( $callback );
			$output = (string) ob_get_clean();

			if ( '' === trim( $output ) ) {
				continue;
			}

			if ( self::is_important( $output ) ) {
				$this->important .= $output;
				continue;
			}

			$this->html  .= $output;
			$this->count += self::count( $output );
		}

		unset( $this->tucked[ $hook ] );
	}

	/**
	 * The bell that opens them, for the header bar. A details element, so it opens without JavaScript.
	 */
	public static function render_toggle(): void {
		$notices = self::$current;

		if ( ! $notices || '' === $notices->html ) {
			return;
		}

		$notices->shown = true;

		$label = sprintf(
			/* translators: %d: number of notifications */
			_n( '%d notification', '%d notifications', $notices->count, 'subkit-subscriptions' ),
			$notices->count
		);

		printf(
			'<details class="subkit-notify" data-subkit-notify><summary class="subkit-notify__bell" aria-label="%s" title="%s">%s<span class="subkit-notify__count" aria-hidden="true">%s</span></summary>'
				. '<div class="subkit-notify__panel" id="subkit-other-notices" role="region" aria-label="%s"><p class="subkit-notify__head">%s</p><div class="subkit-notify__list">',
			esc_attr( $label ),
			esc_attr( $label ),
			self::bell(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html( number_format_i18n( $notices->count ) ),
			esc_attr__( 'Notifications', 'subkit-subscriptions' ),
			esc_html__( 'Notifications', 'subkit-subscriptions' )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- other plugins' notices, printed as WordPress would have printed them.
		echo $notices->html;

		echo '</div></div></details>';
	}

	/**
	 * The important ones, under the header where the page's own content starts.
	 */
	public static function render_important(): void {
		$notices = self::$current;

		if ( ! $notices || $notices->placed ) {
			return;
		}

		$notices->placed = true;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own notices, escaped where they were built.
		echo $notices->important;
	}

	public function render_leftovers(): void {
		self::render_important();

		if ( $this->shown || '' === $this->html ) {
			return;
		}

		$this->shown = true;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- other plugins' notices, printed as WordPress would have printed them.
		echo '<div class="subkit-other-notices">' . $this->html . '</div>';
	}

	public static function bell(): string {
		return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>';
	}

	private static function is_important( string $html ): bool {
		return 1 === preg_match( '/\bclass\s*=\s*(["\'])[^"\']*\b' . preg_quote( self::IMPORTANT, '/' ) . '\b/', $html );
	}

	/**
	 * How many notices one callback printed; anything it printed counts as at least one.
	 */
	private static function count( string $html ): int {
		preg_match_all( '/<div\b[^>]*\bclass\s*=\s*(["\'])(.*?)\1/is', $html, $matches );

		$found = 0;

		foreach ( $matches[2] as $classes ) {
			if ( array_intersect( (array) preg_split( '/\s+/', trim( $classes ) ), array( 'notice', 'error', 'updated' ) ) ) {
				++$found;
			}
		}

		return max( 1, $found );
	}

	/**
	 * @return array<int, callable>
	 */
	private function take( string $hook ): array {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof \WP_Hook ) {
			return array();
		}

		$taken = array();

		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( ! is_callable( $callback['function'] ) ) {
					continue;
				}

				$taken[] = $callback['function'];
				remove_action( $hook, $callback['function'], $priority );
			}
		}

		return $taken;
	}
}
