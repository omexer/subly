<?php

namespace EasySubscription\Checkout;

use EasySubscription\Product\Subscription_Product;

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

	public const OPTION_MIXED = 'easysubscription_allow_mixed_checkout';

	public function register(): void {
		// The Store API runs this filter too, turning the notice into its error.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 6 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_classic_cart' ) );
		add_action( 'woocommerce_store_api_cart_errors', array( $this, 'validate_store_api_cart' ), 10, 1 );
	}

	public static function allows_mixed(): bool {
		return 'no' !== get_option( self::OPTION_MIXED, 'yes' );
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
		if ( ! $passed ) {
			return $passed;
		}

		$refusal = $this->refusal( self::adding_subscription( (int) $product_id, (int) $variation_id, (array) $item_data ) );

		if ( null === $refusal ) {
			return $passed;
		}

		wc_add_notice( $refusal, 'error' );

		return false;
	}

	public function validate_classic_cart(): void {
		if ( $this->is_mixed_and_refused() ) {
			wc_add_notice( self::mixed_cart_message(), 'error' );
		}
	}

	/**
	 * @param \WP_Error $errors
	 */
	public function validate_store_api_cart( $errors ): void {
		if ( $errors instanceof \WP_Error && $this->is_mixed_and_refused() ) {
			$errors->add( 'easysubscription_mixed_cart', self::mixed_cart_message() );
		}
	}

	private function refusal( bool $recurring ): ?string {
		if ( $recurring && Subscription_Product::cart_has_subscription() ) {
			return __( 'You can only sign up for one subscription at a time. Please complete this order first, then start the next subscription.', 'easysubscription' );
		}

		if ( self::allows_mixed() ) {
			return null;
		}

		if ( $recurring && self::cart_has_one_time_item() ) {
			return __( 'Subscriptions are checked out on their own. Please complete the order in your cart first, or empty your cart, then add the subscription.', 'easysubscription' );
		}

		if ( ! $recurring && Subscription_Product::cart_has_subscription() ) {
			return __( 'Subscriptions are checked out on their own. Please complete your subscription order first, then add other products.', 'easysubscription' );
		}

		return null;
	}

	// Checked again at checkout: a cart can outlive the setting it was filled under.
	private function is_mixed_and_refused(): bool {
		return ! self::allows_mixed() && Subscription_Product::cart_has_subscription() && self::cart_has_one_time_item();
	}

	private static function mixed_cart_message(): string {
		return __( 'Subscriptions are checked out on their own. Please remove either the subscription or the other products from your cart.', 'easysubscription' );
	}

	/**
	 * @param array<string, mixed> $item_data
	 */
	public static function adding_subscription( int $product_id, int $variation_id = 0, array $item_data = array() ): bool {
		if ( ! Subscription_Product::is_subscription( $product_id ) ) {
			return false;
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
		return (bool) apply_filters( 'easysubscription_adding_subscription', true, $product_id, $variation_id, $item_data );
	}

	/**
	 * A subscription product bought once counts as one-time, as it does everywhere else.
	 */
	private static function cart_has_one_time_item(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( ! Subscription_Product::is_subscription( $item['data'] ?? null ) || ! apply_filters( 'easysubscription_cart_item_is_subscription', true, $item, $key ) ) {
				return true;
			}
		}

		return false;
	}
}
