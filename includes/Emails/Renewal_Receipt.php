<?php

namespace SubKit\Emails;

use SubKit\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Renewal_Receipt extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subkit_renewal_receipt';
		$this->title          = __( 'Renewal receipt', 'subkit-subscriptions' );
		$this->description    = __( 'Sent to the customer after a renewal payment succeeds.', 'subkit-subscriptions' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your payment went through', 'subkit-subscriptions' );
	}

	public function get_default_heading(): string {
		return __( 'Payment received', 'subkit-subscriptions' );
	}

	public function trigger( Subscription $subscription, \WC_Order $order ): void {
		if ( $this->prepare( $subscription, $order ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		// Says the outcome, not the topic: the amount is the point of this email.
		return sprintf(
			/* translators: %s: amount charged */
			__( "You've been charged %s for your subscription.", 'subkit-subscriptions' ),
			$this->amount( $this->related_order ? $this->related_order->get_total() : $this->subscription->get_total() )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Order', 'subkit-subscriptions' )        => $this->related_order ? '#' . $this->related_order->get_order_number() : '',
			__( 'Amount', 'subkit-subscriptions' )       => $this->amount( $this->related_order ? $this->related_order->get_total() : 0 ),
			__( 'Next payment', 'subkit-subscriptions' ) => $this->date( $this->subscription->get_next_payment() ),
		);
	}

	protected function call_to_action(): ?array {
		return $this->related_order
			? array( 'label' => __( 'View your order', 'subkit-subscriptions' ), 'url' => $this->related_order->get_view_order_url() )
			: null;
	}
}
