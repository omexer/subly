<?php

namespace Subly\Emails;

use Subly\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Renewal_Receipt extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subly_renewal_receipt';
		$this->title          = __( 'Renewal receipt', 'subly' );
		$this->description    = __( 'Sent to the customer after a renewal payment succeeds.', 'subly' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your payment went through', 'subly' );
	}

	public function get_default_heading(): string {
		return __( 'Payment received', 'subly' );
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
			__( "You've been charged %s for your subscription.", 'subly' ),
			$this->amount( $this->related_order ? $this->related_order->get_total() : $this->subscription->get_total() )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Order', 'subly' )        => $this->related_order ? '#' . $this->related_order->get_order_number() : '',
			__( 'Amount', 'subly' )       => $this->amount( $this->related_order ? $this->related_order->get_total() : 0 ),
			__( 'Next payment', 'subly' ) => $this->date( $this->subscription->get_next_payment() ),
		);
	}

	protected function call_to_action(): ?array {
		return $this->related_order
			? array(
				'label' => __( 'View your order', 'subly' ),
				'url'   => $this->related_order->get_view_order_url(),
			)
			: null;
	}
}
