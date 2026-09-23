<?php

namespace SubKit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps other plugins' notices from opening every SubKit screen.
 *
 * They are moved, not dropped: a screen about money should lead with its own state, but
 * hiding a "database update required" warning outright is how a site quietly breaks. Ours
 * and WooCommerce's stay where they are; the rest are folded into one line you can open.
 */
class Notices {

	/**
	 * @var array<int, callable>
	 */
	private array $tucked = array();

	private ?string $html = null;

	private int $count = 0;

	private bool $shown = false;

	private static ?self $current = null;

	public function register(): void {
		self::$current = $this;

		add_action( 'in_admin_header', array( $this, 'collect' ), 1 );
		// Last resort: a SubKit screen that draws no shell would otherwise swallow them.
		add_action( 'admin_footer', array( $this, 'render_panel' ), 99 );
	}

	public function collect(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! str_contains( (string) $screen->id, Menu::SLUG ) ) {
			return;
		}

		foreach ( array( 'admin_notices', 'all_admin_notices', 'user_admin_notices' ) as $hook ) {
			$this->move( $hook );
		}
	}

	/**
	 * The button that opens them, for the header bar.
	 */
	public static function render_toggle(): void {
		$notices = self::$current;

		if ( ! $notices || ! $notices->pending() ) {
			return;
		}

		printf(
			'<button type="button" class="subkit-shell__notices" aria-expanded="false" aria-controls="subkit-other-notices" data-subkit-notices-toggle>'
				. '<span class="subkit-shell__notices-dot" aria-hidden="true"></span>%s</button>',
			esc_html(
				sprintf(
					/* translators: %d: number of notices from other plugins */
					_n( '%d other notice', '%d other notices', $notices->count, 'subkit-subscriptions' ),
					$notices->count
				)
			)
		);
	}

	/**
	 * The notices themselves, closed until the button above is pressed.
	 */
	public function render_panel(): void {
		if ( $this->shown || ! $this->pending() ) {
			return;
		}

		$this->shown = true;

		printf(
			'<div class="subkit-other-notices" id="subkit-other-notices" hidden><div class="subkit-other-notices__inner">%s</div></div>',
			wp_kses_post( (string) $this->html )
		);
	}

	/**
	 * Runs the callbacks once and keeps what they printed.
	 */
	private function pending(): bool {
		if ( null !== $this->html ) {
			return '' !== $this->html;
		}

		$this->html = '';

		foreach ( $this->tucked as $callback ) {
			ob_start();
			call_user_func( $callback );
			$output = (string) ob_get_clean();

			if ( '' !== trim( $output ) ) {
				$this->html .= $output;
				++$this->count;
			}
		}

		return '' !== $this->html;
	}

	private function move( string $hook ): void {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof \WP_Hook ) {
			return;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( ! is_callable( $callback['function'] ) || $this->belongs_here( $callback['function'] ) ) {
					continue;
				}

				$this->tucked[] = $callback['function'];
				remove_action( $hook, $callback['function'], $priority );
			}
		}
	}

	/**
	 * Ours and WooCommerce's: this screen is about them, and Woo's notices are the ones
	 * that stop orders from working.
	 *
	 * @param callable $callback
	 */
	private function belongs_here( $callback ): bool {
		if ( is_array( $callback ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];

			return str_starts_with( $class, 'SubKit' ) || str_starts_with( $class, 'WC_' ) || str_starts_with( $class, 'Automattic\\WooCommerce' );
		}

		return is_string( $callback ) && ( str_starts_with( $callback, 'subkit' ) || str_starts_with( $callback, 'wc_' ) || str_starts_with( $callback, 'woocommerce_' ) );
	}
}
