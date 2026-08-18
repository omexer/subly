<?php

namespace SubKit\Emails;

use SubKit\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Subscription_Started extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subkit_subscription_started';
		$this->title          = __( 'Subscription started', 'subkit-subscriptions' );
		$this->description    = __( 'Sent to the customer when a subscription becomes active.', 'subkit-subscriptions' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your subscription is active', 'subkit-subscriptions' );
	}

	public function get_default_heading(): string {
		return __( 'Your subscription is active', 'subkit-subscriptions' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		return __( 'Thanks for subscribing. Everything is set up and running.', 'subkit-subscriptions' );
	}

	protected function facts(): array {
		$facts = array(
			__( 'Subscription', 'subkit-subscriptions' ) => '#' . $this->subscription->get_id(),
			__( 'Amount', 'subkit-subscriptions' )       => $this->amount( $this->subscription->get_total() ),
		);

		if ( $this->subscription->get_trial_end() ) {
			$facts[ __( 'Free trial ends', 'subkit-subscriptions' ) ] = $this->date( $this->subscription->get_trial_end() );
		}

		$facts[ __( 'Next payment', 'subkit-subscriptions' ) ] = $this->date( $this->subscription->get_next_payment() );

		return $facts;
	}

	protected function call_to_action(): ?array {
		return array( 'label' => __( 'Manage your subscription', 'subkit-subscriptions' ), 'url' => $this->manage_url() );
	}

	protected function outro(): string {
		return __( 'You can cancel at any time from your account.', 'subkit-subscriptions' );
	}
}
