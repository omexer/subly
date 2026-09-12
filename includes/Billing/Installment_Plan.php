<?php

namespace SubKit\Billing;

use SubKit\Domain\Money;
use SubKit\Domain\Subscription;
use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pay a fixed total in N equal charges, then stop.
 *
 * Not a subscription that happens to end: the customer is buying one thing and paying for
 * it over time, so the invariant that matters is that the instalments sum to exactly the
 * price. Money::allocate() distributes the remainder rather than rounding each part, which
 * is why 300.01 over 3 is 100.01 + 100.00 + 100.00 and never 100.00 × 3.
 */
class Installment_Plan {

	public const META_ENABLED = '_subkit_installments_enabled';
	public const META_COUNT   = '_subkit_installments_count';

	public static function is_installment( $product ): bool {
		$product = $product instanceof \WC_Product ? $product : wc_get_product( $product );

		return $product instanceof \WC_Product
			&& Subscription_Product::is_subscription( $product )
			&& 'yes' === $product->get_meta( self::META_ENABLED )
			&& self::count_for( $product ) > 1;
	}

	public static function count_for( $product ): int {
		$product = $product instanceof \WC_Product ? $product : wc_get_product( $product );

		return $product instanceof \WC_Product ? max( 0, (int) $product->get_meta( self::META_COUNT ) ) : 0;
	}

	/**
	 * The per-charge amounts. Always sums back to the total.
	 *
	 * @return Money[]
	 */
	public static function amounts( \WC_Product $product ): array {
		$total = Money::from_decimal( $product->get_price() );

		return $total->allocate( self::count_for( $product ) );
	}

	/**
	 * The amount due for a given charge slot, 1-indexed.
	 */
	public static function amount_for_index( \WC_Product $product, int $period_index ): ?Money {
		$amounts = self::amounts( $product );

		return $amounts[ $period_index ] ?? null;
	}

	public static function total( \WC_Product $product ): Money {
		return Money::from_decimal( $product->get_price() );
	}

	// ------------------------------------------------------------------ subscription side

	public const SUB_META_COUNT = '_subkit_installment_count';

	public static function count_on( Subscription $subscription ): int {
		return (int) $subscription->get_meta( self::SUB_META_COUNT );
	}

	public static function applies_to( Subscription $subscription ): bool {
		return self::count_on( $subscription ) > 1;
	}

	/**
	 * Has the customer paid every instalment?
	 *
	 * period_index 0 is the checkout payment, so a 3-payment plan finishes at index 2.
	 */
	public static function is_complete( Subscription $subscription ): bool {
		$count = self::count_on( $subscription );

		return $count > 1 && ( $subscription->get_period_index() + 1 ) >= $count;
	}

	public static function remaining( Subscription $subscription ): int {
		$count = self::count_on( $subscription );

		return $count > 1 ? max( 0, $count - ( $subscription->get_period_index() + 1 ) ) : 0;
	}

	/**
	 * A human summary for the disclosure and My Account.
	 */
	public static function describe( \WC_Product $product ): string {
		$count   = self::count_for( $product );
		$amounts = self::amounts( $product );

		return sprintf(
			/* translators: 1: number of payments, 2: amount of each payment */
			_n( '%1$d payment of %2$s', '%1$d payments of %2$s', $count, 'subkit-subscriptions' ),
			$count,
			wp_strip_all_tags( $amounts[0]->format() )
		);
	}
}
