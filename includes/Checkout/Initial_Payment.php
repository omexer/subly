<?php

namespace EasySubscription\Checkout;

use EasySubscription\Product\Subscription_Product;

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

			// A line bought once is not on a trial and owes no sign-up fee, whatever the
			// product's own schedule says. Documented on Subscription_Product.
			if ( ! apply_filters( 'easysubscription_cart_item_is_subscription', true, $item, $key ) ) {
				continue;
			}

			if ( ! isset( $this->base_prices[ $key ] ) ) {
				/**
				 * The price per period for this cart line, before today's adjustments.
				 *
				 * The seam for anything that lets one product be bought on more than one
				 * schedule: the choice lives on the cart item, not on the product.
				 *
				 * @param float       $price
				 * @param \WC_Product $product
				 * @param array       $item    The cart item.
				 * @param string      $key     Its cart key.
				 */
				$this->base_prices[ $key ] = (float) apply_filters(
					'easysubscription_cart_recurring_price',
					(float) $product->get_price( 'edit' ),
					$product,
					$item,
					$key
				);
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
