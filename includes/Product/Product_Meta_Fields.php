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

		echo '<div class="options_group subkit-product-options show_if_simple">';

		woocommerce_wp_checkbox( array(
			'id'          => Subscription_Product::META_ENABLED,
			'label'       => __( 'Subscription', 'subkit-subscriptions' ),
			'description' => __( 'Bill this product on a repeating schedule.', 'subkit-subscriptions' ),
			'value'       => $product_object ? $product_object->get_meta( Subscription_Product::META_ENABLED ) : 'no',
		) );

		woocommerce_wp_select( array(
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
		) );

		woocommerce_wp_text_input( array(
			'id'                => Subscription_Product::META_INTERVAL,
			'label'             => __( 'Interval', 'subkit-subscriptions' ),
			'type'              => 'number',
			'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
			'value'             => $product_object ? ( $product_object->get_meta( Subscription_Product::META_INTERVAL ) ?: 1 ) : 1,
			'desc_tip'          => true,
			'description'       => __( 'Bill every N periods. 2 with "Month" means every two months.', 'subkit-subscriptions' ),
		) );

		woocommerce_wp_text_input( array(
			'id'                => Subscription_Product::META_TRIAL_DAYS,
			'label'             => __( 'Free trial (days)', 'subkit-subscriptions' ),
			'type'              => 'number',
			'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			'value'             => $product_object ? ( $product_object->get_meta( Subscription_Product::META_TRIAL_DAYS ) ?: 0 ) : 0,
			'desc_tip'          => true,
			'description'       => __( 'Days before the first payment is taken. 0 charges immediately.', 'subkit-subscriptions' ),
		) );

		woocommerce_wp_text_input( array(
			'id'          => Subscription_Product::META_SIGNUP_FEE,
			/* translators: %s: store currency symbol */
			'label'       => sprintf( __( 'Sign-up fee (%s)', 'subkit-subscriptions' ), get_woocommerce_currency_symbol() ),
			'data_type'   => 'price',
			'value'       => $product_object ? ( $product_object->get_meta( Subscription_Product::META_SIGNUP_FEE ) ?: '' ) : '',
			'desc_tip'    => true,
			'description' => __( 'A one-off charge taken with the first payment.', 'subkit-subscriptions' ),
		) );

		echo '</div>';
	}

	public function save( \WC_Product $product ): void {
		// Nonce is verified by WooCommerce before this hook fires.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$enabled = isset( $_POST[ Subscription_Product::META_ENABLED ] ) ? 'yes' : 'no';

		$product->update_meta_data( Subscription_Product::META_ENABLED, $enabled );

		if ( 'no' === $enabled ) {
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
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
