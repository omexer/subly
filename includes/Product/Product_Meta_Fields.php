<?php

namespace SubKit\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "make this a subscription" panel on the product edit screen.
 *
 * Simple products only in R1; variable products land in R2.
 */
class Product_Meta_Fields {

	public function register(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
	}

	public function render(): void {
		global $product_object;

		printf(
			'<div class="options_group subkit-product-options show_if_%s show_if_%s">',
			esc_attr( Product_Types::SIMPLE ),
			esc_attr( Product_Types::VARIABLE )
		);

		echo '<p class="form-field"><strong>' . esc_html__( 'Billing schedule', 'subkit-subscriptions' ) . '</strong></p>';

		// A legacy product says yes through this; a new one says yes by being the type.
		if ( $product_object && 'yes' === $product_object->get_meta( Subscription_Product::META_ENABLED ) && ! Product_Types::is_subscription_type( $product_object ) ) {
			printf(
				'<p class="form-field"><span class="description">%s</span></p>',
				esc_html__( 'This product bills recurringly but is still a simple product. Change its type to Subscription when convenient; it keeps working either way.', 'subkit-subscriptions' )
			);
		}

		woocommerce_wp_select(
			array(
				'id'          => Subscription_Product::META_PERIOD,
				'label'       => __( 'Bill every', 'subkit-subscriptions' ),
				'options'     => array(
					'day'   => __( 'Day', 'subkit-subscriptions' ),
					'week'  => __( 'Week', 'subkit-subscriptions' ),
					'month' => __( 'Month', 'subkit-subscriptions' ),
					'year'  => __( 'Year', 'subkit-subscriptions' ),
				),
				'value'       => $product_object ? ( $product_object->get_meta( Subscription_Product::META_PERIOD ) ?: 'month' ) : 'month',
				'desc_tip'    => true,
				'description' => __( 'How often the customer is charged.', 'subkit-subscriptions' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => Subscription_Product::META_INTERVAL,
				'label'             => __( 'Interval', 'subkit-subscriptions' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
				'value'             => $product_object ? ( $product_object->get_meta( Subscription_Product::META_INTERVAL ) ?: 1 ) : 1,
				'desc_tip'          => true,
				'description'       => __( 'Bill every N periods. 2 with "Month" means every two months.', 'subkit-subscriptions' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => Subscription_Product::META_TRIAL_DAYS,
				'label'             => __( 'Free trial (days)', 'subkit-subscriptions' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'value'             => $product_object ? ( $product_object->get_meta( Subscription_Product::META_TRIAL_DAYS ) ?: 0 ) : 0,
				'desc_tip'          => true,
				'description'       => __( 'Days before the first payment is taken. 0 charges immediately.', 'subkit-subscriptions' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => Subscription_Product::META_SIGNUP_FEE,
				/* translators: %s: store currency symbol */
				'label'       => sprintf( __( 'Sign-up fee (%s)', 'subkit-subscriptions' ), get_woocommerce_currency_symbol() ),
				'data_type'   => 'price',
				'value'       => $product_object ? ( $product_object->get_meta( Subscription_Product::META_SIGNUP_FEE ) ?: '' ) : '',
				'desc_tip'    => true,
				'description' => __( 'A one-off charge taken with the first payment.', 'subkit-subscriptions' ),
			)
		);

		/**
		 * Fires inside the subscription panel so extensions can add their own fields.
		 *
		 * @param \WC_Product|null $product_object
		 */
		do_action( 'subkit_product_subscription_fields', $product_object );

		echo '</div>';
	}

	public function save( \WC_Product $product ): void {
		// Nonce is verified by WooCommerce before this hook fires.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// The type is the switch now. The meta is still written so anything reading it
		// directly - an older extension, a report - keeps seeing the truth.
		$is_subscription = Product_Types::is_subscription_type( $product );

		$product->update_meta_data( Subscription_Product::META_ENABLED, $is_subscription ? 'yes' : 'no' );

		if ( ! $is_subscription ) {
			return;
		}

		$period = isset( $_POST[ Subscription_Product::META_PERIOD ] )
			? sanitize_text_field( wp_unslash( $_POST[ Subscription_Product::META_PERIOD ] ) )
			: 'month';

		$product->update_meta_data(
			Subscription_Product::META_PERIOD,
			in_array( $period, \SubKit\Domain\Billing_Schedule::PERIODS, true ) ? $period : 'month'
		);

		$product->update_meta_data(
			Subscription_Product::META_INTERVAL,
			max( 1, isset( $_POST[ Subscription_Product::META_INTERVAL ] ) ? absint( wp_unslash( $_POST[ Subscription_Product::META_INTERVAL ] ) ) : 1 )
		);

		$product->update_meta_data(
			Subscription_Product::META_TRIAL_DAYS,
			isset( $_POST[ Subscription_Product::META_TRIAL_DAYS ] ) ? absint( wp_unslash( $_POST[ Subscription_Product::META_TRIAL_DAYS ] ) ) : 0
		);

		$product->update_meta_data(
			Subscription_Product::META_SIGNUP_FEE,
			isset( $_POST[ Subscription_Product::META_SIGNUP_FEE ] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ Subscription_Product::META_SIGNUP_FEE ] ) ) ) : ''
		);
		/**
		 * Fires while saving the subscription panel, for extension fields.
		 *
		 * @param \WC_Product $product
		 */
		do_action( 'subkit_save_product_subscription_fields', $product );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
