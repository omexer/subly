<?php

namespace SubKit\Billing;

use SubKit\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the WooCommerce order that a renewal charge is made against.
 */
class Renewal_Order_Factory {

	/**
	 * Copy the subscription's current line items into a fresh order.
	 *
	 * Items come from the subscription, never from the original order: prices change and
	 * plans get switched, and the subscription is the source of truth. Tax is recalculated
	 * at today's rates against the customer's current address rather than copied.
	 */
	public function create( Subscription $subscription, object $slot ): \WC_Order {
		$order = wc_create_order(
			array(
				'customer_id' => $subscription->get_customer_id(),
				'created_via' => 'subkit_renewal',
			)
		);

		foreach ( $subscription->get_items() as $item ) {
			$copy = new \WC_Order_Item_Product();
			$copy->set_props( array(
				'name'         => $item->get_name(),
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'quantity'     => $item->get_quantity(),
				'subtotal'     => $item->get_subtotal(),
				'total'        => $item->get_total(),
			) );
			$order->add_item( $copy );
		}

		foreach ( $subscription->get_items( 'shipping' ) as $shipping ) {
			$copy = new \WC_Order_Item_Shipping();
			$copy->set_props( array(
				'method_title' => $shipping->get_method_title(),
				'method_id'    => $shipping->get_method_id(),
				'total'        => $shipping->get_total(),
			) );
			$order->add_item( $copy );
		}

		$order->set_address( $subscription->get_address( 'billing' ), 'billing' );
		$order->set_address( $subscription->get_address( 'shipping' ), 'shipping' );
		$order->set_currency( $subscription->get_currency() );
		$order->set_payment_method( $subscription->get_payment_method() );
		$order->set_payment_method_title( $subscription->get_payment_method_title() );

		$order->update_meta_data( '_subkit_subscription_id', $subscription->get_id() );
		$order->update_meta_data( '_subkit_period_index', (int) $slot->period_index );
		$order->update_meta_data( '_subkit_charge_slot_id', (int) $slot->id );
		$order->update_meta_data( '_subkit_covers_from', $slot->covers_from_gmt );
		$order->update_meta_data( '_subkit_covers_to', $slot->covers_to_gmt );

		$order->calculate_taxes();
		$order->calculate_totals( false );
		$order->set_status( 'pending' );
		$order->save();

		/**
		 * Fires once a renewal order has been built but before any charge is attempted.
		 *
		 * @param \WC_Order    $order
		 * @param Subscription $subscription
		 * @param object       $slot
		 */
		do_action( 'subkit_renewal_order_created', $order, $subscription, $slot );

		return $order;
	}
}
