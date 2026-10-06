<?php

namespace Subly\Emails;

use Subly\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Merchant_New_Subscription extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subly_merchant_new_subscription';
		$this->title          = __( 'New subscription (merchant)', 'subly' );
		$this->description    = __( 'Sent to the store when a customer starts a subscription.', 'subly' );
		$this->customer_email = false;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'New subscription started', 'subly' );
	}

	public function get_default_heading(): string {
		return __( 'New subscription', 'subly' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		return sprintf(
			/* translators: 1: customer name, 2: recurring amount */
			__( '%1$s started a subscription worth %2$s.', 'subly' ),
			trim( $this->subscription->get_billing_first_name() . ' ' . $this->subscription->get_billing_last_name() ) ?: $this->subscription->get_billing_email(),
			$this->amount( $this->subscription->get_total() )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'subly' ) => '#' . $this->subscription->get_id(),
			__( 'Customer', 'subly' )     => (string) $this->subscription->get_billing_email(),
			__( 'Next payment', 'subly' ) => $this->date( $this->subscription->get_next_payment() ),
		);
	}
}
