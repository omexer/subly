<?php

namespace SubKit\Checkout;

use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the payment step on a checkout that costs nothing today.
 *
 * A trial with no sign-up fee totals zero, and WooCommerce skips payment entirely for a
 * zero-total order: no gateway runs, so no card is stored, and the first renewal after the
 * trial has nothing to charge. Forcing the payment step collects the mandate up front.
 */
final class Trial_Payment {

	public const OPTION = 'subkit_collect_payment_on_trial';

	public function register(): void {
		add_filter( 'woocommerce_cart_needs_payment', array( $this, 'cart_needs_payment' ), 10, 2 );
		// The block checkout never asks the cart — the Store API asks the draft order instead.
		add_filter( 'woocommerce_order_needs_payment', array( $this, 'order_needs_payment' ), 10, 2 );
	}

	public static function is_enabled(): bool {
		return 'no' !== get_option( self::OPTION, 'yes' );
	}

	/**
	 * @param bool|mixed     $needs_payment
	 * @param \WC_Cart|mixed $cart
	 * @return bool|mixed
	 */
	public function cart_needs_payment( $needs_payment, $cart ) {
		if ( $needs_payment || ! self::is_enabled() || ! $cart instanceof \WC_Cart ) {
			return $needs_payment;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( self::starts_a_trial( $item['data'] ?? null ) ) {
				return true;
			}
		}

		return $needs_payment;
	}

	/**
	 * @param bool|mixed      $needs_payment
	 * @param \WC_Order|mixed $order
	 * @return bool|mixed
	 */
	public function order_needs_payment( $needs_payment, $order ) {
		if ( $needs_payment || ! self::is_enabled() || ! $order instanceof \WC_Order ) {
			return $needs_payment;
		}

		// Only a zero total is ours to override. A false answer for any other reason — an
		// order already paid, or in a status payment cannot apply to — must stay false.
		if ( $order->get_total() > 0 || ! $order->has_status( array( 'pending', 'failed', 'checkout-draft' ) ) ) {
			return $needs_payment;
		}

		if ( 'subkit_renewal' === $order->get_created_via() ) {
			return $needs_payment;
		}

		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product && self::starts_a_trial( $item->get_product() ) ) {
				return true;
			}
		}

		return $needs_payment;
	}

	/**
	 * @param \WC_Product|mixed $product
	 */
	private static function starts_a_trial( $product ): bool {
		return Subscription_Product::is_subscription( $product )
			&& Subscription_Product::schedule( $product )->has_trial();
	}
}
