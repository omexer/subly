<?php

namespace SubKit\Checkout;

use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adding a subscription to the cart goes straight to checkout, when the store turns it on.
 */
class One_Click_Checkout {

	public const OPTION = 'subkit_one_click_checkout';

	public function register(): void {
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'redirect' ), 10, 2 );
		add_filter( 'woocommerce_product_supports', array( $this, 'supports' ), 10, 3 );
	}

	public static function enabled(): bool {
		return 'yes' === get_option( self::OPTION, 'no' );
	}

	/**
	 * @param string|false $url
	 * @param mixed        $product
	 * @return string|false
	 */
	public function redirect( $url, $product = null ) {
		if ( ! self::enabled() || ! $product instanceof \WC_Product || ! Cart_Validation::adding_subscription( $product->get_id() ) ) {
			return $url;
		}

		return wc_get_checkout_url();
	}

	/**
	 * WooCommerce's AJAX button cannot go anywhere but the cart, so it becomes a link to the redirect above.
	 *
	 * @param bool   $supports
	 * @param string $feature
	 * @param mixed  $product
	 */
	public function supports( $supports, $feature, $product ): bool {
		if ( 'ajax_add_to_cart' === $feature && self::enabled() && Subscription_Product::is_subscription( $product ) ) {
			return false;
		}

		return (bool) $supports;
	}
}
