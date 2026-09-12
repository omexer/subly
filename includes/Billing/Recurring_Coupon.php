<?php

namespace SubKit\Billing;

use SubKit\Domain\Money;
use SubKit\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coupons that keep applying to renewals, not just the first order.
 *
 * WooCommerce coupons are a checkout concept — they discount an order and then they are
 * done. A recurring discount has to be re-applied every time a renewal order is built,
 * which is why this listens for the renewal order rather than touching the cart.
 */
class Recurring_Coupon {

	public const META_RECURRING = '_subkit_recurring';
	public const META_LIMIT     = '_subkit_recurring_limit';

	private const SUB_META_CODES = '_subkit_recurring_coupons';
	private const SUB_META_USED  = '_subkit_recurring_coupon_used';

	public function register(): void {
		add_action( 'woocommerce_coupon_options', array( $this, 'render_fields' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save_fields' ), 10, 2 );

		add_action( 'subkit_subscription_created', array( $this, 'capture_from_order' ), 10, 2 );
		add_action( 'subkit_renewal_order_created', array( $this, 'apply_to_renewal' ), 10, 3 );
	}

	public function render_fields( $coupon_id, $coupon ): void {
		woocommerce_wp_checkbox( array(
			'id'          => self::META_RECURRING,
			'label'       => __( 'Applies to renewals', 'subkit-subscriptions' ),
			'description' => __( 'Keep discounting this subscription after the first order.', 'subkit-subscriptions' ),
			'value'       => $coupon instanceof \WC_Coupon ? $coupon->get_meta( self::META_RECURRING ) : 'no',
		) );

		woocommerce_wp_text_input( array(
			'id'                => self::META_LIMIT,
			'label'             => __( 'Renewals to discount', 'subkit-subscriptions' ),
			'type'              => 'number',
			'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			'value'             => $coupon instanceof \WC_Coupon ? ( $coupon->get_meta( self::META_LIMIT ) ?: '' ) : '',
			'desc_tip'          => true,
			'description'       => __( 'Leave empty to discount every renewal. Set 3 to discount the next three and then charge full price.', 'subkit-subscriptions' ),
		) );
	}

	public function save_fields( $coupon_id, $coupon ): void {
		if ( ! $coupon instanceof \WC_Coupon ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies before this hook.
		$coupon->update_meta_data( self::META_RECURRING, isset( $_POST[ self::META_RECURRING ] ) ? 'yes' : 'no' );
		$coupon->update_meta_data(
			self::META_LIMIT,
			isset( $_POST[ self::META_LIMIT ] ) ? absint( wp_unslash( $_POST[ self::META_LIMIT ] ) ) : 0
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$coupon->save();
	}

	/**
	 * Remember which of the order's coupons should carry forward.
	 */
	public function capture_from_order( Subscription $subscription, \WC_Order $order ): void {
		$carry = array();

		foreach ( $order->get_coupon_codes() as $code ) {
			$coupon = new \WC_Coupon( $code );

			if ( 'yes' === $coupon->get_meta( self::META_RECURRING ) ) {
				$carry[] = $code;
			}
		}

		if ( empty( $carry ) ) {
			return;
		}

		$subscription->update_meta_data( self::SUB_META_CODES, $carry );
		$subscription->save();
	}

	/**
	 * @param \WC_Order    $order
	 * @param Subscription $subscription
	 * @param object       $slot
	 */
	public function apply_to_renewal( $order, $subscription, $slot ): void {
		if ( ! $order instanceof \WC_Order || ! $subscription instanceof Subscription ) {
			return;
		}

		$codes = (array) $subscription->get_meta( self::SUB_META_CODES );

		if ( empty( $codes ) ) {
			return;
		}

		$used = (int) $subscription->get_meta( self::SUB_META_USED );

		foreach ( $codes as $code ) {
			$coupon = new \WC_Coupon( $code );
			$limit  = (int) $coupon->get_meta( self::META_LIMIT );

			// 0 means forever; otherwise stop once the allowance is spent.
			if ( $limit > 0 && $used >= $limit ) {
				continue;
			}

			$order->apply_coupon( $coupon );
		}

		$order->calculate_totals( false );
		$order->save();

		$subscription->update_meta_data( self::SUB_META_USED, $used + 1 );
		$subscription->save();
	}

	/**
	 * What the customer will actually pay next, after any carried discount.
	 */
	public function next_amount( Subscription $subscription ): Money {
		return Money::from_decimal( $subscription->get_total(), $subscription->get_currency() );
	}

	public function codes_on( Subscription $subscription ): array {
		return (array) $subscription->get_meta( self::SUB_META_CODES );
	}
}
