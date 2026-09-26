<?php

namespace SubKit\Frontend;

use SubKit\Data\Subscription_Query;
use SubKit\Domain\Subscription_Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Withdraws downloadable files when the subscription that paid for them is not live.
 *
 * The question is whether the customer holds a live subscription for that product, not
 * whether any subscription for it has lapsed - someone who resubscribed after cancelling
 * has both, and must keep their files.
 */
class Downloadable_Access {

	public function register(): void {
		add_filter( 'woocommerce_customer_get_downloadable_products', array( $this, 'filter_customer_downloads' ) );
		add_filter( 'woocommerce_order_get_downloadable_items', array( $this, 'filter_order_downloads' ), 10, 1 );
	}

	public function filter_customer_downloads( $downloads ) {
		return $this->filter( is_array( $downloads ) ? $downloads : array(), get_current_user_id() );
	}

	public function filter_order_downloads( $items ) {
		return $this->filter( is_array( $items ) ? $items : array(), get_current_user_id() );
	}

	private function filter( array $downloads, int $user_id ): array {
		if ( ! $user_id ) {
			return $downloads;
		}

		foreach ( $downloads as $key => $download ) {
			$product_id = (int) ( $download['product_id'] ?? 0 );

			if ( ! $product_id || ! $this->is_subscription_product( $product_id ) ) {
				continue;
			}

			if ( ! $this->has_live_subscription( $user_id, $product_id ) ) {
				unset( $downloads[ $key ] );
			}
		}

		return $downloads;
	}

	private function is_subscription_product( int $product_id ): bool {
		$product = wc_get_product( $product_id );

		return $product instanceof \WC_Product && \SubKit\Product\Subscription_Product::is_subscription( $product );
	}

	private function has_live_subscription( int $user_id, int $product_id ): bool {
		$found = false;

		$subscriptions = Subscription_Query::get(
			array(
				'customer_id' => $user_id,
				'status'      => array( Subscription_Status::Active->value, Subscription_Status::Trialling->value, Subscription_Status::PendingCancel->value, Subscription_Status::OnHold->value ),
				'limit'       => 50,
			)
		);

		foreach ( $subscriptions as $subscription ) {
			if ( Subscription_Status::OnHold === $subscription->get_status_enum() && ! $subscription->in_grace() ) {
				continue;
			}

			foreach ( $subscription->get_items() as $item ) {
				if ( (int) $item->get_product_id() === $product_id || (int) $item->get_variation_id() === $product_id ) {
					$found = true;
					break 2;
				}
			}
		}

		/**
		 * Whether this customer may still have the product's files.
		 *
		 * A lapsed subscription is not always the end of access - a plan bought outright
		 * over a fixed number of payments can grant access beyond its own life - so an
		 * extension gets to widen this answer. It can only ever grant, never revoke.
		 *
		 * @param bool $found
		 * @param int  $user_id
		 * @param int  $product_id
		 */
		return (bool) apply_filters( 'subkit_has_product_access', $found, $user_id, $product_id );
	}
}
