<?php

namespace SubKit\Emails;

use SubKit\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Subscription_Cancelled extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subkit_subscription_cancelled';
		$this->title          = __( 'Subscription cancelled', 'subkit-subscriptions' );
		$this->description    = __( 'Confirmation sent to the customer whenever a subscription is cancelled.', 'subkit-subscriptions' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your subscription has been cancelled', 'subkit-subscriptions' );
	}

	public function get_default_heading(): string {
		return __( 'Your subscription has been cancelled', 'subkit-subscriptions' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		$end = $this->subscription->get_end_date() ?: $this->subscription->get_next_payment();

		if ( 'sk-pending-cancel' === $this->subscription->get_status() && $end ) {
			return sprintf(
				/* translators: %s: date access ends */
				__( "Your subscription is cancelled and won't be charged again. You still have access until %s.", 'subkit-subscriptions' ),
				$this->date( $end )
			);
		}

		return __( "Your subscription is cancelled and won't be charged again.", 'subkit-subscriptions' );
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'subkit-subscriptions' ) => '#' . $this->subscription->get_id(),
		);
	}

	protected function outro(): string {
		return __( 'Changed your mind? You can start a new subscription at any time.', 'subkit-subscriptions' );
	}
}
