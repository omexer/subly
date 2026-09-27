<?php

namespace EasySubscription\Emails;

use EasySubscription\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Subscription_Cancelled extends Subscription_Email {

	public function __construct() {
		$this->id             = 'easysubscription_subscription_cancelled';
		$this->title          = __( 'Subscription cancelled', 'easysubscription' );
		$this->description    = __( 'Confirmation sent to the customer whenever a subscription is cancelled.', 'easysubscription' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your subscription has been cancelled', 'easysubscription' );
	}

	public function get_default_heading(): string {
		return __( 'Your subscription has been cancelled', 'easysubscription' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		$end = $this->subscription->get_end_date() ?: $this->subscription->get_next_payment();

		if ( 'es-pending-cancel' === $this->subscription->get_status() && $end ) {
			return sprintf(
				/* translators: %s: date access ends */
				__( "Your subscription is cancelled and won't be charged again. You still have access until %s.", 'easysubscription' ),
				$this->date( $end )
			);
		}

		return __( "Your subscription is cancelled and won't be charged again.", 'easysubscription' );
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'easysubscription' ) => '#' . $this->subscription->get_id(),
		);
	}

	protected function outro(): string {
		return __( 'Changed your mind? You can start a new subscription at any time.', 'easysubscription' );
	}
}
