<?php

namespace EasySubscription\Emails;

use EasySubscription\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Merchant_New_Subscription extends Subscription_Email {

	public function __construct() {
		$this->id             = 'easysubscription_merchant_new_subscription';
		$this->title          = __( 'New subscription (merchant)', 'easysubscription' );
		$this->description    = __( 'Sent to the store when a customer starts a subscription.', 'easysubscription' );
		$this->customer_email = false;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'New subscription started', 'easysubscription' );
	}

	public function get_default_heading(): string {
		return __( 'New subscription', 'easysubscription' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		return sprintf(
			/* translators: 1: customer name, 2: recurring amount */
			__( '%1$s started a subscription worth %2$s.', 'easysubscription' ),
			trim( $this->subscription->get_billing_first_name() . ' ' . $this->subscription->get_billing_last_name() ) ?: $this->subscription->get_billing_email(),
			$this->amount( $this->subscription->get_total() )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'easysubscription' ) => '#' . $this->subscription->get_id(),
			__( 'Customer', 'easysubscription' )     => (string) $this->subscription->get_billing_email(),
			__( 'Next payment', 'easysubscription' ) => $this->date( $this->subscription->get_next_payment() ),
		);
	}
}
