<?php

namespace Subly\Emails;

use Subly\Domain\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A paused or cancelling subscription is running again, and will be charged again.
 */
class Subscription_Reactivated extends Subscription_Email {

	public function __construct() {
		$this->id             = 'subly_subscription_reactivated';
		$this->title          = __( 'Subscription reactivated', 'subly' );
		$this->description    = __( 'Sent to the customer when a paused or cancelled subscription becomes active again.', 'subly' );
		$this->customer_email = true;

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your subscription is active again', 'subly' );
	}

	public function get_default_heading(): string {
		return __( 'Your subscription is active again', 'subly' );
	}

	public function trigger( Subscription $subscription ): void {
		if ( $this->prepare( $subscription ) ) {
			$this->send_now();
		}
	}

	protected function intro(): string {
		$next = $this->subscription->get_next_payment();

		if ( ! $next ) {
			return __( 'Your subscription is active again.', 'subly' );
		}

		return sprintf(
			/* translators: 1: amount, 2: date */
			__( 'Your subscription is active again. The next payment of %1$s is due on %2$s.', 'subly' ),
			$this->amount( $this->subscription->get_total() ),
			$this->date( $next )
		);
	}

	protected function facts(): array {
		return array(
			__( 'Subscription', 'subly' ) => '#' . $this->subscription->get_id(),
			__( 'Amount', 'subly' )       => $this->amount( $this->subscription->get_total() ),
			__( 'Next payment', 'subly' ) => $this->date( $this->subscription->get_next_payment() ),
		);
	}

	protected function call_to_action(): ?array {
		$url = $this->manage_url();

		return $url ? array(
			'label' => __( 'Manage your subscription', 'subly' ),
			'url'   => $url,
		) : null;
	}

	protected function outro(): string {
		return __( 'You can change or cancel it at any time from your account.', 'subly' );
	}
}
