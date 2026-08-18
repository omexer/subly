<?php

namespace SubKit\Product;

use SubKit\Domain\Billing_Schedule;
use SubKit\Domain\Money;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads subscription configuration off a WooCommerce product.
 *
 * A decorator rather than a custom product type: keeping simple/variable products as
 * they are means every gateway, tax rule and shipping method keeps working untouched.
 */
class Subscription_Product {

	public const META_ENABLED    = '_subkit_enabled';
	public const META_PERIOD     = '_subkit_period';
	public const META_INTERVAL   = '_subkit_interval';
	public const META_TRIAL_DAYS = '_subkit_trial_days';
	public const META_SIGNUP_FEE = '_subkit_signup_fee';

	public static function is_subscription( $product ): bool {
		$product = self::resolve( $product );

		return $product instanceof \WC_Product && 'yes' === $product->get_meta( self::META_ENABLED );
	}

	public static function schedule( $product ): Billing_Schedule {
		$product = self::resolve( $product );

		return new Billing_Schedule(
			$product ? ( $product->get_meta( self::META_PERIOD ) ?: 'month' ) : 'month',
			$product ? max( 1, (int) $product->get_meta( self::META_INTERVAL ) ) : 1,
			$product ? max( 0, (int) $product->get_meta( self::META_TRIAL_DAYS ) ) : 0
		);
	}

	public static function signup_fee( $product ): Money {
		$product = self::resolve( $product );
		$fee     = $product ? (float) $product->get_meta( self::META_SIGNUP_FEE ) : 0.0;

		return Money::from_decimal( $fee );
	}

	public static function recurring_price( $product ): Money {
		$product = self::resolve( $product );

		return Money::from_decimal( $product ? (float) $product->get_price() : 0.0 );
	}

	/**
	 * Does the cart contain anything recurring?
	 */
	public static function cart_has_subscription(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( self::is_subscription( $item['data'] ?? null ) ) {
				return true;
			}
		}

		return false;
	}

	private static function resolve( $product ): ?\WC_Product {
		if ( $product instanceof \WC_Product ) {
			return $product;
		}

		if ( is_numeric( $product ) ) {
			$resolved = wc_get_product( (int) $product );

			return $resolved instanceof \WC_Product ? $resolved : null;
		}

		return null;
	}
}
