<?php

namespace SubKit\Emails;

use SubKit\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A genuine decline. Never used for 3DS challenges - see Confirm_Payment.
 */
class Payment_Failed extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subkit_payment_failed';
		$this->title          = __( 'Payment failed', 'subkit-subscriptions' );
		$this->description    = __( 'Sent to the customer when a renewal payment is declined.', 'subkit-subscriptions' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( "We couldn't process your payment", 'subkit-subscriptions' );
	}

	public function get_default_heading(): string {
		return __( "We couldn't process your payment", 'subkit-subscriptions' );
	}

	public function trigger( Subscription $subscription, \WC_Order $order ): void {
		if ( $this->prepare( $subscription, $order ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		return sprintf(
			/* translators: %s: amount */
			__( "We tried to charge %s for your subscription today, but the payment didn't go through. Your subscription is on hold until it is paid.", 'subkit-subscriptions' ),
			$this->amount( $this->related_order ? $this->related_order->get_total() : $this->subscription->get_total() )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'subkit-subscriptions' ) => '#' . $this->subscription->get_id(),
			__( 'Amount due', 'subkit-subscriptions' )   => $this->amount( $this->related_order ? $this->related_order->get_total() : 0 ),
		);
	}

	protected function call_to_action(): ?array {
		return $this->related_order
			? array(
				'label' => __( 'Pay now', 'subkit-subscriptions' ),
				'url'   => $this->related_order->get_checkout_payment_url(),
			)
			: null;
	}

	protected function outro(): string {
		return __( 'Paying from the link above will restart your subscription straight away. If you have any questions, just reply to this email.', 'subkit-subscriptions' );
	}
}
