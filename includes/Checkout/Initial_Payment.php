<?php

namespace SubKit\Checkout;

use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prices the first payment, which is not the same as the recurring one.
 *
 * A free trial means the product costs nothing today, and a sign-up fee is charged once on
 * top. Both were disclosed to the customer and neither reached the cart, so a "14 days
 * free" product billed its full price immediately and the sign-up fee was never taken.
 */
final class Initial_Payment {

	/** @var array<string, float> Base prices for this request, so a second run cannot re-add the fee. */
	private array $base_prices = array();

	public function register(): void {
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply' ), 20 );
	}

	/**
	 * @param \WC_Cart|mixed $cart
	 */
	public function apply( $cart ): void {
		if ( ! $cart instanceof \WC_Cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $key => $item ) {
			$product = $item['data'] ?? null;

			if ( ! $product instanceof \WC_Product || ! Subscription_Product::is_subscription( $product ) ) {
				continue;
			}

			if ( ! isset( $this->base_prices[ $key ] ) ) {
				$this->base_prices[ $key ] = (float) $product->get_price( 'edit' );
			}

			// Everything that asks what this product costs per period must keep getting the
			// recurring price, not the one-off amount being charged today.
			Subscription_Product::remember_recurring_price( $product, $this->base_prices[ $key ] );

			$product->set_price( (string) self::first_payment( $product, $this->base_prices[ $key ] ) );
		}
	}

	/**
	 * What the customer pays today for one of these.
	 */
	public static function first_payment( \WC_Product $product, float $recurring ): float {
		$fee = (float) Subscription_Product::signup_fee( $product )->decimal();

		return ( Subscription_Product::schedule( $product )->has_trial() ? 0.0 : $recurring ) + $fee;
	}
}
