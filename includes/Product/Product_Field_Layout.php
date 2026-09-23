<?php

namespace SubKit\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Building blocks for the subscription panel, shared with SubKit Pro so its rows match.
 */
final class Product_Field_Layout {

	// Pro's payment type select carries this; without one, the product is recurring.
	public const PAYMENT_TYPE_CLASS = 'subkit-payment-type';

	/**
	 * A titled group whose rows come from $action. Prints nothing when no row does, so a
	 * section Pro fills stays out of the free plugin's panel.
	 *
	 * @param \WC_Product|null $product
	 */
	public static function section( string $id, string $title, string $action, $product, string $wrapper_class = '', bool $collapsed = false ): void {
		ob_start();
		do_action( $action, $product );
		$rows = (string) ob_get_clean();

		if ( '' === trim( $rows ) ) {
			return;
		}

		if ( ! $collapsed ) {
			printf(
				'<div class="options_group subkit-section subkit-section--%1$s %2$s"><h4 class="subkit-section__title">%3$s</h4>%4$s</div>',
				esc_attr( $id ),
				esc_attr( $wrapper_class ),
				esc_html( $title ),
				$rows // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows are escaped by the callbacks that printed them.
			);

			return;
		}

		// A button, not <details>: WooCommerce's panel styles the summary marker badly and
		// the fields must stay in the form either way.
		printf(
			'<div class="options_group subkit-section subkit-section--%1$s subkit-section--collapsed %2$s">'
			. '<h4 class="subkit-section__title"><button type="button" class="subkit-section__toggle" aria-expanded="false" aria-controls="subkit-section-%1$s">%3$s</button></h4>'
			. '<div class="subkit-section__body" id="subkit-section-%1$s" hidden>%4$s</div></div>',
			esc_attr( $id ),
			esc_attr( $wrapper_class ),
			esc_html( $title ),
			$rows // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows are escaped by the callbacks that printed them.
		);
	}

	/**
	 * The class that shows a row only for one payment type.
	 */
	public static function for_type( string $payment_type ): string {
		return 'subkit-for-' . sanitize_html_class( $payment_type );
	}

	/**
	 * Unit choices for a "[number] [unit]" row.
	 *
	 * @return array<string, string>
	 */
	public static function period_choices(): array {
		return array(
			'day'   => __( 'Day(s)', 'subkit-subscriptions' ),
			'week'  => __( 'Week(s)', 'subkit-subscriptions' ),
			'month' => __( 'Month(s)', 'subkit-subscriptions' ),
			'year'  => __( 'Year(s)', 'subkit-subscriptions' ),
		);
	}

	/**
	 * A number and its unit on one row, e.g. "Bill every [1] [Month(s)]".
	 *
	 * @param array{
	 *     label: string,
	 *     number_id: string,
	 *     number_value: string|int,
	 *     unit_id: string,
	 *     unit_value: string,
	 *     units?: array<string, string>,
	 *     min?: int,
	 *     max?: int,
	 *     placeholder?: string,
	 *     tip?: string,
	 *     note?: string,
	 *     when?: string,
	 *     wrapper_class?: string,
	 * } $args
	 */
	public static function duration( array $args ): void {
		$units = $args['units'] ?? self::period_choices();

		echo '<p class="form-field subkit-duration ' . esc_attr( $args['wrapper_class'] ?? '' ) . '">';
		printf( '<label for="%1$s">%2$s</label>', esc_attr( $args['number_id'] ), esc_html( $args['label'] ) );
		// WooCommerce's "short" sizes the pair like a single field, at every breakpoint it defines.
		echo '<span class="subkit-duration__inputs short">';

		printf(
			'<input type="number" class="short" id="%1$s" name="%1$s" value="%2$s" min="%3$d" step="1"%4$s placeholder="%5$s"%6$s />',
			esc_attr( $args['number_id'] ),
			esc_attr( (string) $args['number_value'] ),
			(int) ( $args['min'] ?? 0 ),
			isset( $args['max'] ) ? ' max="' . (int) $args['max'] . '"' : '',
			esc_attr( $args['placeholder'] ?? '' ),
			isset( $args['when'] ) ? ' data-subkit-when="' . esc_attr( $args['when'] ) . '"' : ''
		);

		printf(
			'<select id="%1$s" name="%1$s" aria-label="%2$s">',
			esc_attr( $args['unit_id'] ),
			/* translators: %s: field label such as "Bill every" */
			esc_attr( sprintf( __( '%s unit', 'subkit-subscriptions' ), $args['label'] ) )
		);
		foreach ( $units as $value => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $args['unit_value'], $value, false ), esc_html( $label ) );
		}
		echo '</select></span>';

		if ( ! empty( $args['tip'] ) ) {
			echo wc_help_tip( $args['tip'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip escapes.
		}

		if ( ! empty( $args['note'] ) ) {
			echo '<span class="description subkit-field-note">' . esc_html( $args['note'] ) . '</span>';
		}

		echo '</p>';
	}
}
