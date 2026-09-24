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

	public const META_ENABLED  = '_subkit_enabled';
	public const META_PERIOD   = '_subkit_period';
	public const META_INTERVAL = '_subkit_interval';
	// The trial length in META_TRIAL_PERIOD units; named from when trials were days only.
	public const META_TRIAL_DAYS   = '_subkit_trial_days';
	public const META_TRIAL_PERIOD = '_subkit_trial_period';
	public const META_SIGNUP_FEE   = '_subkit_signup_fee';

	public static function is_subscription( $product ): bool {
		$product = self::resolve( $product );

		if ( ! $product instanceof \WC_Product ) {
			return false;
		}

		// The product type is the answer for anything made since the types existed. The
		// meta still counts, so products created when this was a checkbox keep billing.
		return Product_Types::is_subscription_type( $product )
			|| 'yes' === self::meta( $product, self::META_ENABLED );
	}

	public static function schedule( $product ): Billing_Schedule {
		$product      = self::resolve( $product );
		$trial_period = (string) self::meta( $product, self::META_TRIAL_PERIOD, 'day' );

		return new Billing_Schedule(
			self::meta( $product, self::META_PERIOD ) ?: 'month',
			max( 1, (int) self::meta( $product, self::META_INTERVAL, 1 ) ),
			max( 0, (int) self::meta( $product, self::META_TRIAL_DAYS, 0 ) ),
			null,
			in_array( $trial_period, Billing_Schedule::PERIODS, true ) ? $trial_period : 'day'
		);
	}

	public static function signup_fee( $product ): Money {
		$product = self::resolve( $product );

		return Money::from_decimal( (float) self::meta( $product, self::META_SIGNUP_FEE, 0.0 ) );
	}

	/**
	 * Every read of subscription configuration goes through here.
	 *
	 * The single seam an extension needs: a variation carries no parent meta of its own,
	 * so without this each caller would have to know to look up the parent, and the ones
	 * that forgot would silently treat the variation as not a subscription.
	 *
	 * @param mixed $default
	 * @return mixed
	 */
	public static function meta( ?\WC_Product $product, string $key, $default = '' ) {
		$value = $product ? $product->get_meta( $key ) : '';

		/**
		 * Filter a single piece of subscription configuration read off a product.
		 *
		 * @param mixed            $value
		 * @param \WC_Product|null $product
		 * @param string           $key
		 */
		$value = apply_filters( 'subkit_product_meta', $value, $product, $key );

		return '' === $value || null === $value ? $default : $value;
	}

	/** @var array<int, float> Set when the cart reprices a product for its first payment. */
	private static array $recurring_prices = array();

	public static function remember_recurring_price( \WC_Product $product, float $price ): void {
		self::$recurring_prices[ $product->get_id() ] = $price;
	}

	/**
	 * The price per billing period.
	 *
	 * The cart lowers a trial product's price to what is due today, so the product object
	 * alone stops being a reliable answer once a checkout is underway.
	 */
	public static function recurring_price( $product ): Money {
		$product = self::resolve( $product );

		if ( ! $product instanceof \WC_Product ) {
			return Money::from_decimal( 0.0 );
		}

		return Money::from_decimal( self::$recurring_prices[ $product->get_id() ] ?? (float) $product->get_price() );
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
