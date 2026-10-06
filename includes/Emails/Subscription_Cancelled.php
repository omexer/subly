<?php

namespace Subly\Emails;

use Subly\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Subscription_Cancelled extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subly_subscription_cancelled';
		$this->title          = __( 'Subscription cancelled', 'subly' );
		$this->description    = __( 'Confirmation sent to the customer whenever a subscription is cancelled.', 'subly' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your subscription has been cancelled', 'subly' );
	}

	public function get_default_heading(): string {
		return __( 'Your subscription has been cancelled', 'subly' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		$end = $this->subscription->get_end_date() ?: $this->subscription->get_next_payment();

		if ( 'subly-cancelling' === $this->subscription->get_status() && $end ) {
			return sprintf(
				/* translators: %s: date access ends */
				__( "Your subscription is cancelled and won't be charged again. You still have access until %s.", 'subly' ),
				$this->date( $end )
			);
		}

		return __( "Your subscription is cancelled and won't be charged again.", 'subly' );
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'subly' ) => '#' . $this->subscription->get_id(),
		);
	}

	protected function outro(): string {
		return __( 'Changed your mind? You can start a new subscription at any time.', 'subly' );
	}
}
