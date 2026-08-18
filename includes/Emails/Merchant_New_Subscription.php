<?php

namespace SubKit\Emails;

use SubKit\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Merchant_New_Subscription extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subkit_merchant_new_subscription';
		$this->title          = __( 'New subscription (merchant)', 'subkit-subscriptions' );
		$this->description    = __( 'Sent to the store when a customer starts a subscription.', 'subkit-subscriptions' );
		$this->customer_email = false;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'New subscription started', 'subkit-subscriptions' );
	}

	public function get_default_heading(): string {
		return __( 'New subscription', 'subkit-subscriptions' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		return sprintf(
			/* translators: 1: customer name, 2: recurring amount */
			__( '%1$s started a subscription worth %2$s.', 'subkit-subscriptions' ),
			trim( $this->subscription->get_billing_first_name() . ' ' . $this->subscription->get_billing_last_name() ) ?: $this->subscription->get_billing_email(),
			$this->amount( $this->subscription->get_total() )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'subkit-subscriptions' ) => '#' . $this->subscription->get_id(),
			__( 'Customer', 'subkit-subscriptions' )     => (string) $this->subscription->get_billing_email(),
			__( 'Next payment', 'subkit-subscriptions' ) => $this->date( $this->subscription->get_next_payment() ),
		);
	}
}
