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
 * Only the layout is ours: WooCommerce's sections are gathered into the menu's groups.
 */
class Settings_Page {

	public const SLUG = Menu::SLUG . '-settings';

	private const NONCE = 'subkit_save_settings';

	/**
	 * A section missing from every group's list lands in Integrations.
	 */
	private const FALLBACK_GROUP = 'integrations';

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
	 * sanitisation - the same path as its settings screen. Every section drawn on the
	 * page is saved, since a group stacks several into one form.
	 */
	public function maybe_save(): void {
		if ( ! isset( $_POST['subkit_settings_nonce'] ) || ! current_user_can( Menu::CAPABILITY ) ) {
			return;
		}

		check_admin_referer( self::NONCE, 'subkit_settings_nonce' );

		$section  = isset( $_POST['subkit_section'] ) ? sanitize_key( wp_unslash( $_POST['subkit_section'] ) ) : '';
		$sections = $this->sections();

		if ( ! isset( $sections[ $section ] ) ) {
			return;
		}

		$saved = false;

		foreach ( $this->page_sections( $section, $sections ) as $id ) {
			$fields = $this->settings_for( $id );

			if ( $fields ) {
				\WC_Admin_Settings::save_fields( $fields );
				$saved = true;
			}
		}

		if ( ! $saved ) {
			return;
		}

		wp_safe_redirect( add_query_arg( 'subkit_saved', '1', self::section_url( $section ) ) );
		exit;
	}

	public function render(): void {
		$sections = $this->sections();
		$current  = $this->current_section( $sections );

		Page_Shell::open( __( 'Settings', 'subkit-subscriptions' ), '', array( array( 'label' => __( 'Settings', 'subkit-subscriptions' ) ) ), '', false );

		$this->render_notices();

		if ( ! $sections ) {
			printf(
				'<div class="subkit-notice subkit-notice--bad">%s</div>',
				esc_html__( "WooCommerce's settings could not be loaded, so EasySubscription's settings cannot be shown. Check that WooCommerce is active.", 'subkit-subscriptions' )
			);
			Page_Shell::close();

			return;
		}

		$on_page = $this->page_sections( $current, $sections );
		$fields  = array();

		foreach ( $on_page as $id ) {
			$fields[ $id ] = $this->settings_for( $id );
		}

		echo '<div class="subkit-settings">';

		$this->render_nav( $sections, $current );

		echo '<form class="subkit-settings__main" method="post" action="">';
		wp_nonce_field( self::NONCE, 'subkit_settings_nonce' );
		printf( '<input type="hidden" name="subkit_section" value="%s" />', esc_attr( $current ) );

		$this->render_cards( $fields );

		printf(
			'<div class="subkit-settings__save"><span class="subkit-settings__save-note">%s</span>'
				. '<button type="submit" class="button button-primary subkit-btn subkit-btn--primary">%s</button></div>',
			esc_html__( 'Changes apply to new renewals and purchases from the moment you save.', 'subkit-subscriptions' ),
			esc_html__( 'Save changes', 'subkit-subscriptions' )
		);

		echo '</form></div>';

		Page_Shell::close();
	}

	/**
	 * The menu's groups, in menu order. A stacked group draws all its sections on one page;
	 * a listed one draws one section at a time, chosen from a list under the group.
	 *
	 * @return array<string, array{label: string, icon: string, sections: string[], list?: bool}>
	 */
	public static function groups(): array {
		return array(
			'general'            => array(
				'label'    => __( 'General', 'subkit-subscriptions' ),
				'icon'     => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
				'sections' => array( '', 'license' ),
			),
			'customers'          => array(
				'label'    => __( 'Customer Controls', 'subkit-subscriptions' ),
				'icon'     => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="10" r="3"/><path d="M6.2 18.4a6.5 6.5 0 0 1 11.6 0"/>',
				'sections' => array( 'customer_controls' ),
			),
			'billing'            => array(
				'label'    => __( 'Renewal & Billing', 'subkit-subscriptions' ),
				'icon'     => '<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M14.5 8h-3.2a1.3 1.3 0 0 0 0 2.6h1.4a1.3 1.3 0 0 1 0 2.6H9.5M12 6.5V8m0 5.2v1.5"/>',
				'sections' => array( 'recovery', 'renewal' ),
			),
			'switching'          => array(
				'label'    => __( 'Upgrade & Downgrade', 'subkit-subscriptions' ),
				'icon'     => '<path d="M7 20V4m0 0L3.5 7.5M7 4l3.5 3.5M17 4v16m0 0 3.5-3.5M17 20l-3.5-3.5"/>',
				'sections' => array( 'upsell' ),
			),
			'checkout'           => array(
				'label'    => __( 'Cart & Checkout', 'subkit-subscriptions' ),
				'icon'     => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.7 12.2a1 1 0 0 0 1 .8h9.6a1 1 0 0 0 1-.8L21 7H6"/>',
				'sections' => array( 'checkout' ),
			),
			'shipping'           => array(
				'label'    => __( 'Shipping', 'subkit-subscriptions' ),
				'icon'     => '<path d="M2.5 6h11v10h-11zM13.5 9.5h4l3 3V16h-7"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
				'sections' => array( 'live-qr' ),
			),
			'notifications'      => array(
				'label'    => __( 'Notifications', 'subkit-subscriptions' ),
				'icon'     => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
				'sections' => array( 'payment_methods' ),
			),
			'payments'           => array(
				'label'    => __( 'Payments', 'subkit-subscriptions' ),
				'icon'     => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6.5 15h3"/>',
				'sections' => array( 'stripe', 'paypal', 'mollie', 'razorpay', 'xendit', 'square', 'authorize_net', 'braintree', 'adyen', 'gocardless', 'woopayments', 'paddle', 'bkash', 'sslcommerz' ),
				'list'     => true,
			),
			self::FALLBACK_GROUP => array(
				'label'    => __( 'Integrations', 'subkit-subscriptions' ),
				'icon'     => '<path d="M9 2.5V7m6-4.5V7M6 7h12v4.5a6 6 0 0 1-12 0zM12 17.5v4"/>',
				'sections' => array( 'whatsapp', 'affiliatewp', 'anniversary', 'winback', 'content' ),
				'list'     => true,
			),
		);
	}

	/**
	 * Which group a section is drawn in.
	 */
	public static function group_of( string $section ): string {
		foreach ( self::groups() as $id => $group ) {
			if ( in_array( $section, $group['sections'], true ) ) {
				return $id;
			}
		}

		return self::FALLBACK_GROUP;
	}

	/**
	 * The group's sections that exist on this site, in the group's order, then any the
	 * group only holds as the fallback, in the order they were registered.
	 *
	 * @param array<string, string> $sections
	 * @return string[]
	 */
	private function group_sections( string $group, array $sections ): array {
		$listed = self::groups()[ $group ]['sections'] ?? array();
		$found  = array_values( array_filter( $listed, static fn( string $id ): bool => isset( $sections[ $id ] ) ) );

		foreach ( array_keys( $sections ) as $id ) {
			if ( self::group_of( (string) $id ) === $group && ! in_array( (string) $id, $found, true ) ) {
				$found[] = (string) $id;
			}
		}

		return $found;
	}

	/**
	 * @param array<string, string> $sections
	 * @return string[]
	 */
	private function page_sections( string $current, array $sections ): array {
		$group = self::group_of( $current );

		return empty( self::groups()[ $group ]['list'] ) ? $this->group_sections( $group, $sections ) : array( $current );
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
		$active = self::group_of( $current );

		echo '<nav class="subkit-settings__nav" aria-label="' . esc_attr__( 'Settings sections', 'subkit-subscriptions' ) . '"><ul>';

		foreach ( self::groups() as $id => $group ) {
			$members = $this->group_sections( $id, $sections );

			// A group with nothing in it on this site is left out rather than drawn empty.
			if ( ! $members ) {
				continue;
			}

			$here    = $id === $active;
			$listing = ! empty( $group['list'] ) && count( $members ) > 1;

			printf(
				'<li><a class="subkit-settings__tab%s" href="%s"%s><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg><span>%s</span></a>',
				$here ? ' is-current' : '',
				esc_url( self::section_url( $members[0] ) ),
				$here && ! $listing ? ' aria-current="page"' : '',
				$group['icon'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
				esc_html( $group['label'] )
			);

			if ( $here && $listing ) {
				echo '<ul class="subkit-settings__subnav">';

				foreach ( $members as $section ) {
					printf(
						'<li><a class="subkit-settings__subtab%s" href="%s"%s>%s</a></li>',
						$section === $current ? ' is-current' : '',
						esc_url( self::section_url( $section ) ),
						$section === $current ? ' aria-current="page"' : '',
						esc_html( (string) $sections[ $section ] )
					);
				}

				echo '</ul>';
			}

			echo '</li>';
		}

		echo '</ul></nav>';
	}

	/**
	 * WooCommerce's settings array is a flat list where a "title" opens a group and
	 * "sectionend" closes it. Each group becomes a card; a page of one card needs no heading,
	 * since the menu already names it.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $by_section
	 */
	private function render_cards( array $by_section ): void {
		$all     = array_merge( ...array_values( $by_section ) );
		$toggles = $this->toggle_states( $all );
		$joined  = $this->joined_fields( $all );
		$cards   = array();

		foreach ( $by_section as $section => $fields ) {
			$card  = null;
			$rows  = '';
			$first = true;

			foreach ( $fields as $field ) {
				$type = (string) ( $field['type'] ?? '' );

				if ( 'title' === $type || 'sectionend' === $type ) {
					if ( '' !== trim( $rows ) ) {
						$cards[] = array( $card, $rows, $first ? (string) $section : null );
						$first   = false;
					}

					$rows = '';
					$card = 'title' === $type
						? array( (string) ( $field['title'] ?? '' ), (string) ( $field['desc'] ?? '' ) )
						: null;
					continue;
				}

				if ( isset( $field['subkit_joins'] ) ) {
					continue;
				}

				ob_start();
				$this->render_row( $field, $toggles, $joined[ (string) ( $field['id'] ?? '' ) ] ?? array() );
				$rows .= (string) ob_get_clean();
			}

			if ( '' !== trim( $rows ) ) {
				$cards[] = array( $card, $rows, $first ? (string) $section : null );
			}
		}

		$titled = count( $cards ) > 1;

		foreach ( $cards as list( $card, $rows, $anchor ) ) {
			printf( '<section class="subkit-settings__card"%s>', null === $anchor ? '' : ' id="' . esc_attr( 'subkit-section-' . ( '' === $anchor ? 'general' : $anchor ) ) . '"' );

			$title = $titled ? (string) ( $card[0] ?? '' ) : '';
			$desc  = (string) ( $card[1] ?? '' );

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

			echo '<div class="subkit-settings__rows">' . $rows . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where each row was built.
		}
	}

	/**
	 * Every switch on the page and whether it is on, so a row that depends on one starts
	 * out hidden when it is off.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 * @return array<string, array{on: bool, parent: string}>
	 */
	private function toggle_states( array $fields ): array {
		$toggles = array();

		foreach ( $fields as $field ) {
			if ( 'checkbox' === ( $field['type'] ?? '' ) && ! empty( $field['id'] ) ) {
				$toggles[ (string) $field['id'] ] = array(
					'on'     => 'yes' === \WC_Admin_Settings::get_option( (string) $field['id'], $field['default'] ?? '' ),
					'parent' => (string) ( $field['subkit_show_if'] ?? '' ),
				);
			}
		}

		return $toggles;
	}

	/**
	 * Fields drawn beside another field's control instead of on a row of their own, such as
	 * a unit beside a number.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function joined_fields( array $fields ): array {
		$joined = array();

		foreach ( $fields as $field ) {
			if ( isset( $field['subkit_joins'] ) ) {
				$joined[ (string) $field['subkit_joins'] ][] = $field;
			}
		}

		return $joined;
	}

	/**
	 * @param array<string, array{on: bool, parent: string}> $toggles
	 */
	private function is_shown( string $parent, array $toggles, int $depth = 0 ): bool {
		if ( '' === $parent || ! isset( $toggles[ $parent ] ) || $depth > 10 ) {
			return true;
		}

		return $toggles[ $parent ]['on'] && $this->is_shown( $toggles[ $parent ]['parent'], $toggles, $depth + 1 );
	}

	/**
	 * @param array<string, mixed>                            $field
	 * @param array<string, array{on: bool, parent: string}> $toggles
	 * @param array<int, array<string, mixed>>                $joined
	 */
	private function render_row( array $field, array $toggles, array $joined ): void {
		$id     = (string) ( $field['id'] ?? '' );
		$type   = (string) ( $field['type'] ?? 'text' );
		$help   = $this->help_text( $field );
		$parent = (string) ( $field['subkit_show_if'] ?? '' );

		if ( 'subkit_status' === $type ) {
			echo '<div class="subkit-settings__row subkit-settings__row--wide"><div class="subkit-checks">';

			foreach ( Settings::status_checks() as $check ) {
				Settings::render_check( $check );
			}

			echo '</div></div>';

			return;
		}

		if ( '' === $id ) {
			$markup = $this->woo_markup( $field );

			// A custom row with nothing to say, such as the tax repair with nothing to repair, draws nothing.
			if ( '' !== $markup ) {
				echo '<div class="subkit-settings__row subkit-settings__row--wide">' . $markup . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's own field output.
			}

			return;
		}

		printf(
			'<div class="subkit-settings__row%s"%s>',
			$this->is_shown( $parent, $toggles ) ? '' : ' is-hidden',
			'' === $parent ? '' : ' data-subkit-show-if="' . esc_attr( $parent ) . '"'
		);
		printf( '<div class="subkit-settings__label"><label class="subkit-settings__title" for="%s">%s</label>', esc_attr( $id ), esc_html( (string) ( $field['title'] ?? '' ) ) );
		if ( '' !== $help ) {
			printf( '<p class="subkit-settings__help" id="%s">%s</p>', esc_attr( $id . '-help' ), wp_kses_post( $help ) );
		}
		echo '</div><div class="subkit-settings__control">';

		$this->render_control( $field, '' !== $help );

		foreach ( $joined as $extra ) {
			printf( '<label class="screen-reader-text" for="%s">%s</label>', esc_attr( (string) ( $extra['id'] ?? '' ) ), esc_html( (string) ( $extra['title'] ?? '' ) ) );
			$this->render_control( $extra, false );
		}

		if ( '' !== $this->suffix( $field ) ) {
			printf( '<span class="subkit-settings__suffix">%s</span>', wp_kses_post( $this->suffix( $field ) ) );
		}

		echo '</div></div>';
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private function render_control( array $field, bool $described ): void {
		$id    = (string) ( $field['id'] ?? '' );
		$type  = (string) ( $field['type'] ?? 'text' );
		$value = array_key_exists( 'value', $field )
			? (string) $field['value']
			: (string) \WC_Admin_Settings::get_option( $id, $field['default'] ?? '' );
		$aria  = $described ? sprintf( ' aria-describedby="%s"', esc_attr( $id . '-help' ) ) : '';

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<span class="subkit-switch"><input type="checkbox" role="switch" name="%1$s" id="%1$s" value="1"%2$s%3$s /><span class="subkit-switch__track" aria-hidden="true"></span></span>',
					esc_attr( $id ),
					checked( $value, 'yes', false ),
					$aria // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built.
				);
				break;

			case 'select':
				printf( '<select name="%1$s" id="%1$s" class="subkit-input"%2$s>', esc_attr( $id ), $aria ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built.
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
					'<textarea name="%1$s" id="%1$s" class="subkit-input subkit-input--wide" rows="4"%2$s%3$s>%4$s</textarea>',
					esc_attr( $id ),
					$aria, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built.
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
					'<input type="%1$s" name="%2$s" id="%2$s" class="subkit-input%3$s" value="%4$s"%5$s%6$s />',
					esc_attr( $type ),
					esc_attr( $id ),
					'number' === $type ? ' subkit-input--short' : ' subkit-input--wide',
					esc_attr( $value ),
					$aria, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built.
					$this->attributes( $field ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in attributes().
				);
				break;

			default:
				echo $this->woo_markup( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's own field output.
		}
	}

	/**
	 * Anything this screen has no markup for - a custom type another plugin registered -
	 * is handed back to WooCommerce so it still renders and still saves.
	 *
	 * @param array<string, mixed> $field
	 */
	private function woo_markup( array $field ): string {
		ob_start();
		\WC_Admin_Settings::output_fields( array( $field ) );
		$markup = trim( (string) ob_get_clean() );

		return '' === $markup ? '' : '<table class="form-table subkit-settings__woo">' . $markup . '</table>';
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private function help_text( array $field ): string {
		$desc = '' === $this->suffix( $field ) ? trim( (string) ( $field['desc'] ?? '' ) ) : '';
		$tip  = isset( $field['desc_tip'] ) && is_string( $field['desc_tip'] ) ? trim( $field['desc_tip'] ) : '';

		if ( '' === $desc || '' === $tip ) {
			return $desc . $tip;
		}

		// WooCommerce draws these apart, so a checkbox's label rarely ends a sentence.
		return ( preg_match( '/[.!?:]$/u', $desc ) ? $desc : $desc . '.' ) . ' ' . $tip;
	}

	/**
	 * A number's description is the unit WooCommerce prints after it, such as "days ahead",
	 * unless it is shown as the tooltip.
	 *
	 * @param array<string, mixed> $field
	 */
	private function suffix( array $field ): string {
		return 'number' === ( $field['type'] ?? '' ) && true !== ( $field['desc_tip'] ?? false ) ? trim( (string) ( $field['desc'] ?? '' ) ) : '';
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
		// No include: WooCommerce's autoloader maps wc_admin* to includes/admin/, so asking
		// for the class is what loads it.
		if ( ! class_exists( '\WC_Admin_Settings' ) ) {
			return null;
		}

		foreach ( \WC_Admin_Settings::get_settings_pages() as $page ) {
			if ( $page instanceof \WC_Settings_Page && 'subkit' === $page->get_id() ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * Any section's address, including one drawn stacked inside a group.
	 */
	public static function section_url( string $section = '' ): string {
		$args = array( 'page' => self::SLUG );

		if ( '' !== $section ) {
			$args['section'] = $section;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
