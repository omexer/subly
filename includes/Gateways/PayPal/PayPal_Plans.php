<?php

namespace SubKit\Gateways\PayPal;

use SubKit\Domain\Billing_Schedule;
use SubKit\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps a WooCommerce product onto a PayPal product + billing plan.
 *
 * PayPal will not bill an arbitrary amount on a schedule; it bills a *plan* it already
 * knows about. So every subscription product needs a plan created once at PayPal and
 * cached against the product, and a new plan whenever price or schedule changes.
 */
class PayPal_Plans {

	private const META_PRODUCT = '_subkit_paypal_product_id';
	private const META_PLAN    = '_subkit_paypal_plan_id';
	private const META_HASH    = '_subkit_paypal_plan_hash';

	public function __construct( private readonly PayPal_Client $client ) {}

	/**
	 * The plan id for this product, creating it at PayPal if needed.
	 *
	 * @return array{ok: bool, plan_id: string, error: string}
	 */
	public function plan_for( \WC_Product $product ): array {
		$hash = $this->fingerprint( $product );

		$cached = (string) $product->get_meta( self::META_PLAN );
		if ( '' !== $cached && $hash === (string) $product->get_meta( self::META_HASH ) ) {
			return $this->ok( $cached );
		}

		$paypal_product = $this->paypal_product_for( $product );
		if ( ! $paypal_product['ok'] ) {
			return $this->fail( $paypal_product['error'] );
		}

		$plan = $this->create_plan( $product, $paypal_product['id'] );
		if ( ! $plan['ok'] ) {
			return $this->fail( $plan['error'] );
		}

		$product->update_meta_data( self::META_PLAN, $plan['id'] );
		$product->update_meta_data( self::META_HASH, $hash );
		$product->save();

		return $this->ok( $plan['id'] );
	}

	/**
	 * Price and schedule together. A change to either needs a new plan, because PayPal
	 * plans are immutable in the ways that matter to us.
	 */
	private function fingerprint( \WC_Product $product ): string {
		$schedule = Subscription_Product::schedule( $product );

		return sha1(
			implode(
				'|',
				array(
					$product->get_price(),
					get_woocommerce_currency(),
					$schedule->period(),
					$schedule->interval(),
					$schedule->trial_length(),
					$schedule->trial_period(),
					(string) Subscription_Product::signup_fee( $product )->minor(),
				)
			)
		);
	}

	/**
	 * @return array{ok: bool, id: string, error: string}
	 */
	private function paypal_product_for( \WC_Product $product ): array {
		$existing = (string) $product->get_meta( self::META_PRODUCT );
		if ( '' !== $existing ) {
			return array(
				'ok'    => true,
				'id'    => $existing,
				'error' => '',
			);
		}

		$response = $this->client->post(
			'/v1/catalogs/products',
			array(
				'name'     => wp_strip_all_tags( $product->get_name() ),
				'type'     => $product->is_virtual() ? 'DIGITAL' : 'PHYSICAL',
				'category' => 'MERCHANDISE',
			)
		);

		if ( ! $response['ok'] || empty( $response['body']['id'] ) ) {
			return array(
				'ok'    => false,
				'id'    => '',
				'error' => $response['error'],
			);
		}

		$product->update_meta_data( self::META_PRODUCT, $response['body']['id'] );
		$product->save();

		return array(
			'ok'    => true,
			'id'    => (string) $response['body']['id'],
			'error' => '',
		);
	}

	/**
	 * @return array{ok: bool, id: string, error: string}
	 */
	private function create_plan( \WC_Product $product, string $paypal_product_id ): array {
		$schedule = Subscription_Product::schedule( $product );
		$currency = get_woocommerce_currency();
		$cycles   = array();
		$sequence = 1;

		if ( $schedule->has_trial() ) {
			$cycles[] = array(
				'tenure_type'    => 'TRIAL',
				'sequence'       => $sequence++,
				'total_cycles'   => 1,
				'frequency'      => array(
					'interval_unit'  => $this->paypal_unit( $schedule->trial_period() ),
					'interval_count' => $schedule->trial_length(),
				),
				'pricing_scheme' => array(
					'fixed_price' => array(
						'value'         => '0',
						'currency_code' => $currency,
					),
				),
			);
		}

		/**
		 * How many payments PayPal should take before it stops on its own.
		 *
		 * PayPal owns this schedule, so a limit SubKit knows about — a payment cap, an
		 * instalment plan — has to be built into the plan or PayPal bills past the end.
		 *
		 * @param int          $total_cycles 0 bills forever.
		 * @param \WC_Product  $product
		 */
		$total_cycles = max( 0, (int) apply_filters( 'subkit_paypal_total_cycles', 0, $product ) );

		$cycles[] = array(
			'tenure_type'    => 'REGULAR',
			'sequence'       => $sequence,
			// 0 means bill forever, which is what an open-ended subscription is.
			'total_cycles'   => $total_cycles,
			'frequency'      => array(
				'interval_unit'  => $this->interval_unit( $schedule ),
				'interval_count' => $schedule->interval(),
			),
			'pricing_scheme' => array(
				'fixed_price' => array(
					'value'         => wc_format_decimal( $product->get_price(), wc_get_price_decimals() ),
					'currency_code' => $currency,
				),
			),
		);

		$payload = array(
			'product_id'          => $paypal_product_id,
			'name'                => wp_strip_all_tags( $product->get_name() ),
			'billing_cycles'      => $cycles,
			'payment_preferences' => array(
				'auto_bill_outstanding'     => true,
				'setup_fee_failure_action'  => 'CANCEL',
				'payment_failure_threshold' => 3,
			),
		);

		$fee = Subscription_Product::signup_fee( $product );
		if ( ! $fee->is_zero() ) {
			$payload['payment_preferences']['setup_fee'] = array(
				'value'         => $fee->decimal(),
				'currency_code' => $currency,
			);
		}

		$response = $this->client->post( '/v1/billing/plans', $payload );

		if ( ! $response['ok'] || empty( $response['body']['id'] ) ) {
			return array(
				'ok'    => false,
				'id'    => '',
				'error' => $response['error'],
			);
		}

		return array(
			'ok'    => true,
			'id'    => (string) $response['body']['id'],
			'error' => '',
		);
	}

	private function interval_unit( Billing_Schedule $schedule ): string {
		return $this->paypal_unit( $schedule->period() );
	}

	private function paypal_unit( string $period ): string {
		return match ( $period ) {
			'day'   => 'DAY',
			'week'  => 'WEEK',
			'year'  => 'YEAR',
			default => 'MONTH',
		};
	}

	private function ok( string $plan_id ): array {
		return array(
			'ok'      => true,
			'plan_id' => $plan_id,
			'error'   => '',
		);
	}

	private function fail( string $error ): array {
		return array(
			'ok'      => false,
			'plan_id' => '',
			'error'   => $error,
		);
	}
}
