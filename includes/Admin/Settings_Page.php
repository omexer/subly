<?php

namespace SubKit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SubKit → Settings.
 *
 * The fields are WooCommerce's own definitions, read from the settings tab and its filters,
 * so free and Pro declare a setting once and it appears here, saved by Woo's own handler.
 * Only the layout is ours.
 */
class Settings_Page {

	public const SLUG = Menu::SLUG . '-settings';

	private const NONCE = 'subkit_save_settings';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 95 );
	}

	public function add_page(): void {
		$hook = add_submenu_page(
			Menu::PARENT,
			__( 'Settings', 'subkit-subscriptions' ),
			__( 'Settings', 'subkit-subscriptions' ),
			Menu::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);

		// This screen's own load, not admin_init: the licence buttons post to admin-post.php
		// carrying this form's fields, and an admin_init handler would answer for them.
		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'maybe_save' ) );
		}
	}

	/**
	 * Saved by WooCommerce, which reads $_POST itself and applies each field's own
	 * sanitisation - the same path as its settings screen.
	 */
	public function maybe_save(): void {
		if ( ! isset( $_POST['subkit_settings_nonce'] ) || ! current_user_can( Menu::CAPABILITY ) ) {
			return;
		}

		check_admin_referer( self::NONCE, 'subkit_settings_nonce' );

		$section = isset( $_POST['subkit_section'] ) ? sanitize_key( wp_unslash( $_POST['subkit_section'] ) ) : '';
		$fields  = $this->settings_for( $section );

		if ( ! $fields ) {
			return;
		}

		\WC_Admin_Settings::save_fields( $fields );

		wp_safe_redirect( add_query_arg( 'subkit_saved', '1', $this->url( $section ) ) );
		exit;
	}

	public function render(): void {
		$sections = $this->sections();
		$current  = $this->current_section( $sections );
		$fields   = $this->settings_for( $current );

		Page_Shell::open(
			__( 'Settings', 'subkit-subscriptions' ),
			__( 'How SubKit bills, what a subscription grants, and which gateways it can use.', 'subkit-subscriptions' ),
			array( array( 'label' => __( 'Settings', 'subkit-subscriptions' ) ) )
		);

		$this->render_notices();

		echo '<div class="subkit-settings">';

		$this->render_nav( $sections, $current );

		echo '<form class="subkit-settings__main" method="post" action="">';
		wp_nonce_field( self::NONCE, 'subkit_settings_nonce' );
		printf( '<input type="hidden" name="subkit_section" value="%s" />', esc_attr( $current ) );

		$this->render_fields( $fields );

		printf(
			'<div class="subkit-settings__save"><span class="subkit-settings__save-note">%s</span>'
				. '<button type="submit" class="button button-primary subkit-btn subkit-btn--primary">%s</button></div>',
			esc_html__( 'Changes apply to new renewals and purchases from the moment you save.', 'subkit-subscriptions' ),
			esc_html__( 'Save changes', 'subkit-subscriptions' )
		);

		echo '</form>';

		$this->render_rail();

		echo '</div>';

		Page_Shell::close();
	}

	/**
	 * What just happened, if anything: a save of our own, or a licence action that came
	 * back here from SubKit Pro.
	 */
	private function render_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- redirect flags, not actions.
		if ( isset( $_GET['subkit_saved'] ) ) {
			printf( '<div class="subkit-notice subkit-notice--good">%s</div>', esc_html__( 'Settings saved.', 'subkit-subscriptions' ) );
		}

		$licence = isset( $_GET['subkit_license_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['subkit_license_notice'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $licence ) {
			return;
		}

		$known = array(
			'activated'   => __( 'Licence activated.', 'subkit-subscriptions' ),
			'deactivated' => __( 'Licence deactivated.', 'subkit-subscriptions' ),
			'ok'          => __( 'Licence updated.', 'subkit-subscriptions' ),
		);

		printf(
			'<div class="subkit-notice subkit-notice--%s">%s</div>',
			isset( $known[ $licence ] ) ? 'good' : 'bad',
			esc_html( $known[ $licence ] ?? $licence )
		);
	}

	/**
	 * @param array<string, string> $sections
	 */
	private function render_nav( array $sections, string $current ): void {
		echo '<nav class="subkit-settings__nav" aria-label="' . esc_attr__( 'Settings sections', 'subkit-subscriptions' ) . '">';

		foreach ( $sections as $id => $label ) {
			printf(
				'<a class="subkit-settings__tab%1$s" href="%2$s"%3$s>%4$s<span class="subkit-settings__tab-text">'
					. '<strong>%5$s</strong><em>%6$s</em></span></a>',
				$id === $current ? ' is-current' : '',
				esc_url( $this->url( (string) $id ) ),
				$id === $current ? ' aria-current="page"' : '',
				self::icon( (string) $id ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
				esc_html( (string) $label ),
				esc_html( $this->describe( (string) $id ) )
			);
		}

		echo '</nav>';
	}

	/**
	 * WooCommerce's settings array is a flat list where a "title" opens a group and
	 * "sectionend" closes it. Each group becomes a card.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 */
	private function render_fields( array $fields ): void {
		$card = null;
		$rows = '';

		foreach ( $fields as $field ) {
			$type = (string) ( $field['type'] ?? '' );

			if ( 'title' === $type || 'sectionend' === $type ) {
				$this->print_card( $card, $rows );
				$rows = '';
				$card = 'title' === $type
					? array( (string) ( $field['title'] ?? '' ), (string) ( $field['desc'] ?? '' ) )
					: null;
				continue;
			}

			// The status readout lives in the rail on this screen, next to the quick actions.
			if ( 'subkit_status' === $type ) {
				continue;
			}

			ob_start();
			$this->render_row( $field );
			$rows .= (string) ob_get_clean();
		}

		$this->print_card( $card, $rows );
	}

	/**
	 * A card with no rows is not drawn: a heading over nothing reads as a screen that
	 * failed to load.
	 *
	 * @param array{0: string, 1: string}|null $card
	 */
	private function print_card( ?array $card, string $rows ): void {
		if ( '' === trim( $rows ) ) {
			return;
		}

		$this->open_card( $card[0] ?? '', $card[1] ?? '' );
		echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where each row was built.
		$this->close_card( true );
	}

	private function open_card( string $title, string $desc ): void {
		echo '<section class="subkit-card subkit-settings__card">';

		if ( '' !== $title || '' !== $desc ) {
			echo '<header class="subkit-settings__card-head">';
			if ( '' !== $title ) {
				printf( '<h2>%s</h2>', esc_html( $title ) );
			}
			if ( '' !== $desc ) {
				printf( '<p>%s</p>', wp_kses_post( $desc ) );
			}
			echo '</header>';
		}

		echo '<div class="subkit-settings__rows">';
	}

	private function close_card( bool $open ): void {
		if ( $open ) {
			echo '</div></section>';
		}
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private function render_row( array $field ): void {
		$id    = (string) ( $field['id'] ?? '' );
		$type  = (string) ( $field['type'] ?? 'text' );
		$label = (string) ( $field['title'] ?? '' );
		$help  = $this->help_text( $field );

		if ( '' === $id ) {
			$this->render_unknown( $field );

			return;
		}

		$value = array_key_exists( 'value', $field )
			? (string) $field['value']
			: (string) \WC_Admin_Settings::get_option( $id, $field['default'] ?? '' );

		echo '<div class="subkit-settings__row">';
		printf( '<div class="subkit-settings__label"><label for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );
		if ( '' !== $help ) {
			printf( '<p class="subkit-settings__help">%s</p>', wp_kses_post( $help ) );
		}
		echo '</div><div class="subkit-settings__control">';

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<label class="subkit-switch"><input type="checkbox" name="%1$s" id="%1$s" value="1"%2$s /><span class="subkit-switch__track"></span></label>',
					esc_attr( $id ),
					checked( $value, 'yes', false )
				);
				break;

			case 'select':
				printf( '<select name="%1$s" id="%1$s" class="subkit-input">', esc_attr( $id ) );
				foreach ( (array) ( $field['options'] ?? array() ) as $option => $option_label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( (string) $option ),
						selected( $value, (string) $option, false ),
						esc_html( (string) $option_label )
					);
				}
				echo '</select>';
				break;

			case 'textarea':
				printf(
					'<textarea name="%1$s" id="%1$s" class="subkit-input" rows="4"%2$s>%3$s</textarea>',
					esc_attr( $id ),
					$this->attributes( $field ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in attributes().
					esc_textarea( $value )
				);
				break;

			case 'text':
			case 'password':
			case 'number':
			case 'email':
			case 'url':
				printf(
					'<input type="%1$s" name="%2$s" id="%2$s" class="subkit-input" value="%3$s"%4$s />',
					esc_attr( $type ),
					esc_attr( $id ),
					esc_attr( $value ),
					$this->attributes( $field ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in attributes().
				);
				break;

			default:
				$this->render_unknown( $field );
		}

		echo '</div></div>';
	}

	/**
	 * Anything this screen has no markup for - a custom type another plugin registered -
	 * is handed back to WooCommerce so it still renders and still saves.
	 *
	 * @param array<string, mixed> $field
	 */
	private function render_unknown( array $field ): void {
		echo '<table class="form-table subkit-settings__woo">';
		\WC_Admin_Settings::output_fields( array( $field ) );
		echo '</table>';
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private function help_text( array $field ): string {
		$desc = (string) ( $field['desc'] ?? '' );
		$tip  = isset( $field['desc_tip'] ) && is_string( $field['desc_tip'] ) ? $field['desc_tip'] : '';

		return trim( $desc . ( '' !== $desc && '' !== $tip ? ' ' : '' ) . $tip );
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private function attributes( array $field ): string {
		$out = '';

		foreach ( (array) ( $field['custom_attributes'] ?? array() ) as $name => $value ) {
			$out .= sprintf( ' %s="%s"', esc_attr( (string) $name ), esc_attr( (string) $value ) );
		}

		if ( isset( $field['placeholder'] ) ) {
			$out .= sprintf( ' placeholder="%s"', esc_attr( (string) $field['placeholder'] ) );
		}

		return $out;
	}

	private function render_rail(): void {
		echo '<aside class="subkit-settings__rail">';

		printf(
			'<section class="subkit-card"><header class="subkit-settings__card-head"><h2>%s</h2></header><div class="subkit-settings__links">',
			esc_html__( 'Quick actions', 'subkit-subscriptions' )
		);

		$links = array(
			array(
				'label' => __( 'Set up a payment gateway', 'subkit-subscriptions' ),
				'desc'  => __( 'Stripe, PayPal and the rest', 'subkit-subscriptions' ),
				'url'   => $this->url( 'stripe' ),
			),
			array(
				'label' => __( 'Integrations', 'subkit-subscriptions' ),
				'desc'  => __( 'Connect the plugins you already run', 'subkit-subscriptions' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::SLUG . '-integrations' ),
			),
			array(
				'label' => __( 'Help and system report', 'subkit-subscriptions' ),
				'desc'  => __( 'What to check first, and what to send us', 'subkit-subscriptions' ),
				'url'   => admin_url( 'admin.php?page=' . Menu::SLUG . '-help' ),
			),
		);

		foreach ( $links as $link ) {
			printf(
				'<a class="subkit-settings__link" href="%s"><span><strong>%s</strong><em>%s</em></span><span aria-hidden="true">&rarr;</span></a>',
				esc_url( $link['url'] ),
				esc_html( $link['label'] ),
				esc_html( $link['desc'] )
			);
		}

		echo '</div></section>';

		printf(
			'<section class="subkit-card"><header class="subkit-settings__card-head"><h2>%s</h2><p>%s</p></header><div class="subkit-checks">',
			esc_html__( 'System status', 'subkit-subscriptions' ),
			esc_html__( 'Whether renewals can actually run.', 'subkit-subscriptions' )
		);

		foreach ( Settings::status_checks() as $check ) {
			printf(
				'<div class="subkit-check subkit-check--%s"><span class="subkit-check__dot"></span><div>'
					. '<span class="subkit-check__label">%s</span><span class="subkit-check__detail">%s</span></div></div>',
				$check['ok'] ? 'ok' : 'bad',
				esc_html( $check['label'] ),
				esc_html( $check['ok'] ? $check['good'] : $check['bad'] )
			);
		}

		echo '</div></section></aside>';
	}

	/**
	 * @return array<string, string>
	 */
	private function sections(): array {
		$page = $this->woo_page();

		return $page ? (array) $page->get_sections() : array();
	}

	/**
	 * @param array<string, string> $sections
	 */
	private function current_section( array $sections ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen routing.
		$asked = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';

		return isset( $sections[ $asked ] ) ? $asked : '';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function settings_for( string $section ): array {
		$page = $this->woo_page();

		return $page ? (array) $page->get_settings_for_section( $section ) : array();
	}

	private function woo_page(): ?\WC_Settings_Page {
		if ( ! class_exists( '\WC_Admin_Settings' ) ) {
			include_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
		}

		foreach ( \WC_Admin_Settings::get_settings_pages() as $page ) {
			if ( $page instanceof \WC_Settings_Page && 'subkit' === $page->get_id() ) {
				return $page;
			}
		}

		return null;
	}

	private function url( string $section ): string {
		$args = array( 'page' => self::SLUG );

		if ( '' !== $section ) {
			$args['section'] = $section;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * The section's own first group description, so Pro's sections describe themselves.
	 */
	private function describe( string $section ): string {
		if ( '' === $section ) {
			return __( 'Renewals, what a subscription grants, and guest checkout.', 'subkit-subscriptions' );
		}

		foreach ( $this->settings_for( $section ) as $field ) {
			if ( 'title' === ( $field['type'] ?? '' ) && ! empty( $field['desc'] ) ) {
				return wp_trim_words( wp_strip_all_tags( (string) $field['desc'] ), 10 );
			}
		}

		return '';
	}

	private static function icon( string $section ): string {
		$paths = array(
			''       => '<path d="M10 2.5a1 1 0 0 1 1 1v.6a5.5 5.5 0 0 1 1.5.9l.5-.3a1 1 0 0 1 1.4.4l.5.9a1 1 0 0 1-.4 1.3l-.5.3a5.5 5.5 0 0 1 0 1.7l.5.3a1 1 0 0 1 .4 1.3l-.5.9a1 1 0 0 1-1.4.4l-.5-.3a5.5 5.5 0 0 1-1.5.9v.6a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-.6a5.5 5.5 0 0 1-1.5-.9l-.5.3a1 1 0 0 1-1.4-.4l-.5-.9a1 1 0 0 1 .4-1.3l.5-.3a5.5 5.5 0 0 1 0-1.7l-.5-.3a1 1 0 0 1-.4-1.3l.5-.9a1 1 0 0 1 1.4-.4l.5.3A5.5 5.5 0 0 1 8 4.1v-.6a1 1 0 0 1 1-1z"/><circle cx="9.5" cy="10" r="2.2" fill="#fff"/>',
			'paypal' => '<path d="M4 4h6.2c2.2 0 3.6 1.2 3.3 3.2-.3 2.1-1.9 3.3-4.1 3.3H7.9L7.3 15H4.6L4 4z"/>',
			'stripe' => '<path d="M6 6.6c0-.6.6-.9 1.4-.9 1 0 2.2.3 3.2.8V4.1A8 8 0 0 0 7.5 3.5C5 3.5 3.3 4.8 3.3 6.8c0 3.2 4.3 2.7 4.3 4.1 0 .6-.5.9-1.4.9-1 0-2.5-.4-3.5-1v2.5c1 .4 2.2.7 3.4.7 2.6 0 4.4-1.2 4.4-3.3 0-3.4-4.5-2.8-4.5-4.1z"/>',
		);

		$path = $paths[ $section ] ?? '<rect x="3.5" y="3.5" width="13" height="13" rx="3.5"/>';

		return '<span class="subkit-settings__icon" aria-hidden="true"><svg viewBox="0 0 20 20" width="18" height="18" fill="currentColor">' . $path . '</svg></span>';
	}
}
