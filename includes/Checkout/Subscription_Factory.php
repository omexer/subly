<?php

namespace EasySubscription\Checkout;

use EasySubscription\Billing\Renewal_Scheduler;
use EasySubscription\Data\Activity_Repository;
use EasySubscription\Data\Charge_Slot_Repository;
use EasySubscription\Domain\Subscription;
use EasySubscription\Domain\Subscription_Status;
use EasySubscription\Product\Subscription_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a completed checkout into a subscription.
 */
class Subscription_Factory {

	public function __construct(
		private readonly Charge_Slot_Repository $slots,
		private readonly Activity_Repository $activity,
		private readonly Renewal_Scheduler $scheduler
	) {}

	public function register(): void {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'from_checkout' ), 10, 1 );
		// Block checkout goes through the Store API, which fires its own hook and passes
		// the order object rather than an ID. Without this, blocks create no subscription.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'from_store_api' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'activate_for_order' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'activate_for_order' ), 20, 1 );
	}

	/**
	 * @param int $order_id
	 */
	public function from_checkout( $order_id ): void {
		$order = wc_get_order( (int) $order_id );

		if ( ! $order instanceof \WC_Order || $this->existing_for_order( $order ) ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			if ( ! $product || ! Subscription_Product::is_subscription( $product ) ) {
				continue;
			}

			/**
			 * Whether this line starts a subscription.
			 *
			 * A subscription product can be sold as a one-off — buy it once, or subscribe
			 * and save — and that choice is made on the line, not on the product.
			 *
			 * @param bool            $create
			 * @param \WC_Order_Item  $item
			 * @param \WC_Product     $product
			 * @param \WC_Order       $order
			 */
			if ( ! apply_filters( 'easysubscription_create_subscription_for_item', true, $item, $product, $order ) ) {
				continue;
			}

			$this->create( $order, $item, $product );

			// One subscription per order in R1; the cart rule already enforces this.
			break;
		}
	}

	/**
	 * @param \WC_Order $order
	 */
	public function from_store_api( $order ): void {
		if ( $order instanceof \WC_Order ) {
			$this->from_checkout( $order->get_id() );
		}
	}

	private function create( \WC_Order $order, $item, \WC_Product $product ): Subscription {
		$schedule = Subscription_Product::schedule( $product );
		$now      = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		$subscription = new Subscription();
		$subscription->set_parent_order_id( $order->get_id() );
		$subscription->set_customer_id( $order->get_customer_id() );
		$subscription->set_currency( $order->get_currency() );
		$subscription->set_payment_method( $order->get_payment_method() );
		$subscription->set_payment_method_title( $order->get_payment_method_title() );
		$subscription->set_address( $order->get_address( 'billing' ), 'billing' );
		$subscription->set_address( $order->get_address( 'shipping' ), 'shipping' );

		$subscription->set_billing_period( $schedule->period() );
		$subscription->set_billing_interval( $schedule->interval() );

		$trial_end = $schedule->trial_end_from( $now );
		if ( $trial_end ) {
			$subscription->set_trial_end( $trial_end->format( 'Y-m-d H:i:s' ) );
		}

		// A trial is charged the day it ends, which is what the customer was told; without
		// a trial the first renewal is one full period from today, the initial payment
		// having been the parent order.
		$first_renewal = $trial_end ?? $schedule->next_date_from( $now );
		$subscription->set_next_payment( $first_renewal->format( 'Y-m-d H:i:s' ) );

		// Pin the origin site so a cloned staging copy refuses to bill real customers.
		$subscription->update_meta_data( '_easysubscription_site_url', get_option( 'siteurl' ) );

		// The recurring amount, not what the first order came to: a trial makes that zero
		// and a one-off coupon would otherwise discount every renewal for ever.
		$quantity  = max( 1, (int) $item->get_quantity() );
		$recurring = $this->line_total_excluding_tax( $order, $product, $quantity );

		$copy = new \WC_Order_Item_Product();
		$copy->set_props(
			array(
				'name'         => $item->get_name(),
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'quantity'     => $quantity,
				'subtotal'     => $recurring,
				'total'        => $recurring,
			)
		);
		$subscription->add_item( $copy );

		$subscription->transition_to(
			$schedule->has_trial() ? Subscription_Status::Trialling : Subscription_Status::Pending
		);
		// WooCommerce's own flag, which calculate_taxes() reads; checkout set it for an exempt customer.
		$subscription->update_meta_data( 'is_vat_exempt', 'yes' === $order->get_meta( 'is_vat_exempt' ) ? 'yes' : 'no' );

		// Renewals recalculate tax the same way, so the subscription shows what they will charge.
		$subscription->calculate_totals( true );
		$subscription->save();

		// Slot 0 is the initial checkout payment, so the first renewal is index 1.
		$this->slots->claim(
			$subscription->get_id(),
			0,
			$now,
			$now,
			$trial_end ?? $schedule->next_date_from( $now )
		);
		$slot = $this->slots->find( $subscription->get_id(), 0 );
		if ( $slot ) {
			$this->slots->mark_paid( (int) $slot->id, $order->get_id() );
		}

		$order->update_meta_data( '_easysubscription_subscription_id', $subscription->get_id() );
		$order->save();

		$this->activity->log(
			$subscription->get_id(),
			Activity_Repository::TYPE_STATUS_CHANGE,
			sprintf( 'Subscription created from order #%s.', $order->get_order_number() )
		);

		/**
		 * Fires when a subscription has been created from a checkout.
		 *
		 * @param Subscription $subscription
		 * @param \WC_Order    $order
		 */
		/**
		 * Fires once the subscription exists, so extensions can stamp their own
		 * configuration onto it from the product it was bought from.
		 *
		 * @param Subscription $subscription
		 * @param \WC_Product  $product
		 */
		do_action( 'easysubscription_configure_subscription', $subscription, $product );

		do_action( 'easysubscription_subscription_created', $subscription, $order );

		return $subscription;
	}

	/**
	 * Line totals are stored without tax, as WooCommerce's cart stored the checkout's; renewals add tax back for the customer's address.
	 */
	private function line_total_excluding_tax( \WC_Order $order, \WC_Product $product, int $quantity ): string {
		$unit = Subscription_Product::recurring_price( $product )->decimal();

		return wc_format_decimal(
			wc_get_price_excluding_tax(
				$product,
				array(
					'qty'   => $quantity,
					'price' => $unit,
					'order' => $order,
				)
			)
		);
	}

	/**
	 * Activate once the parent order is actually paid.
	 *
	 * @param int $order_id
	 */
	public function activate_for_order( $order_id ): void {
		$order = wc_get_order( (int) $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$subscription = $this->existing_for_order( $order );
		if ( ! $subscription ) {
			return;
		}

		// Renewal orders carry the same meta. Only the parent order starts a subscription;
		// without this, every successful renewal re-fires the "subscription started" flow.
		if ( $order->get_id() !== $subscription->get_parent_order_id() ) {
			return;
		}

		$status = $subscription->get_status_enum();
		$target = $subscription->get_trial_end() ? Subscription_Status::Trialling : Subscription_Status::Active;

		if ( $status && $status->can_transition_to( $target ) ) {
			$subscription->transition_to( $target );
			$subscription->save();
		}

		$this->scheduler->schedule_next( $subscription );

		do_action( 'easysubscription_subscription_activated', $subscription );
	}

	private function existing_for_order( \WC_Order $order ): ?Subscription {
		$id = (int) $order->get_meta( '_easysubscription_subscription_id' );

		if ( ! $id ) {
			return null;
		}

		$subscription = wc_get_order( $id );

		return $subscription instanceof Subscription ? $subscription : null;
	}
}
