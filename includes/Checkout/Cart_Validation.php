<?php

namespace SubKit\Checkout;

use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart rules for recurring items.
 *
 * R1 allows one subscription per cart. Many gateways cannot hold more than one mandate
 * per order at all, and the ones that can create a support burden out of proportion to
 * the demand. Pro relaxes this per gateway once supports('multiple_subs') exists.
 */
class Cart_Validation {

	public function register(): void {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 6 );
	}

	/**
	 * @param bool  $passed
	 * @param int   $product_id
	 * @param int   $quantity
	 * @param int   $variation_id
	 * @param array $variations
	 * @param array $item_data
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity = 1, $variation_id = 0, $variations = array(), $item_data = array() ) {
		if ( ! $passed || ! Subscription_Product::is_subscription( $product_id ) ) {
			return $passed;
		}

		/**
		 * Whether the line being added is recurring, for a product that can also be
		 * bought once. The choice is in the request, not on the product.
		 *
		 * @param bool  $recurring
		 * @param int   $product_id
		 * @param int   $variation_id
		 * @param array $item_data
		 */
		if ( ! apply_filters( 'subkit_adding_subscription', true, $product_id, $variation_id, $item_data ) ) {
			return $passed;
		}

		if ( ! Subscription_Product::cart_has_subscription() ) {
			return $passed;
		}

		wc_add_notice(
			__( 'You can only sign up for one subscription at a time. Please complete this order first, then start the next subscription.', 'subkit-subscriptions' ),
			'error'
		);

		return false;
	}
}
